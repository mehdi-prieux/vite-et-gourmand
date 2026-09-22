#!/usr/bin/env bash

set -euo pipefail

if [[ "${DB_NAME:-}" != "vite_gourmand_test" ]]; then
  echo "STOP: DB_NAME doit valoir vite_gourmand_test." >&2
  exit 42
fi

project_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
port="${TEST_HTTP_PORT:-8766}"
base_url="http://127.0.0.1:${port}/backend/api"
run_id="${RANDOM}.$$.${RANDOM}"
client_email="codex.client.${run_id}@example.test"
staff_email="codex.staff.${run_id}@example.test"
managed_email="codex.managed.${run_id}@example.test"
test_menu_title="Menu Codex ${run_id}"
test_dish_name="Plat Codex ${run_id}"
test_day="Codex${RANDOM}"
client_password='Client!Passw0rd2026'
new_client_password='NewClient!Passw0rd2026'
staff_password='Staff!Passw0rd2026'
order_date=$(php -r 'echo (new DateTimeImmutable("+30 days", new DateTimeZone("Europe/Paris")))->format("Y-m-d");')
modified_order_date=$(php -r 'echo (new DateTimeImmutable("+31 days", new DateTimeZone("Europe/Paris")))->format("Y-m-d");')
cancel_order_date=$(php -r 'echo (new DateTimeImmutable("+32 days", new DateTimeZone("Europe/Paris")))->format("Y-m-d");')
client_cookie=$(mktemp)
staff_cookie=$(mktemp)
server_log=$(mktemp)
mail_log=$(mktemp)
server_pid=''

verify_database() {
  php -r '
    require $argv[1] . "/backend/config/database.php";
    $actual = $pdo->query("SELECT DATABASE()")->fetchColumn();
    if ($actual !== "vite_gourmand_test") {
        fwrite(STDERR, "STOP: base réellement utilisée: " . var_export($actual, true) . PHP_EOL);
        exit(42);
    }
  ' "$project_root"
}

cleanup_database() {
  TEST_CLIENT_EMAIL="$client_email" TEST_STAFF_EMAIL="$staff_email" TEST_MANAGED_EMAIL="$managed_email" TEST_MENU_TITLE="$test_menu_title" TEST_DISH_NAME="$test_dish_name" TEST_DAY="$test_day" php -r '
    require $argv[1] . "/backend/config/database.php";
    if ($pdo->query("SELECT DATABASE()")->fetchColumn() !== "vite_gourmand_test") {
        fwrite(STDERR, "STOP: nettoyage refusé hors vite_gourmand_test." . PHP_EOL);
        exit(42);
    }
    $emails = [getenv("TEST_CLIENT_EMAIL"), getenv("TEST_STAFF_EMAIL"), getenv("TEST_MANAGED_EMAIL")];
    $query = $pdo->prepare("SELECT utilisateur_id FROM utilisateur WHERE email IN (?, ?, ?)");
    $query->execute($emails);
    $userIds = $query->fetchAll(PDO::FETCH_COLUMN);
    if ($userIds) {
      $userMarks = implode(",", array_fill(0, count($userIds), "?"));
    $query = $pdo->prepare("SELECT commande_id, menu_id, statut FROM commande WHERE utilisateur_id IN ($userMarks)");
    $query->execute($userIds);
    $orders = $query->fetchAll(PDO::FETCH_ASSOC);
    $orderIds = array_column($orders, "commande_id");
    if ($orderIds) {
        $orderMarks = implode(",", array_fill(0, count($orderIds), "?"));
        foreach (["suivi_commande", "commande_plat", "avis", "intervention_commande"] as $table) {
            $pdo->prepare("DELETE FROM $table WHERE commande_id IN ($orderMarks)")->execute($orderIds);
        }
        $pdo->prepare("DELETE FROM commande WHERE commande_id IN ($orderMarks)")->execute($orderIds);
        $restore = $pdo->prepare("UPDATE menu SET stock = stock + 1 WHERE menu_id = ?");
        foreach ($orders as $order) {
            if ($order["statut"] !== "annulée") {
                $restore->execute([$order["menu_id"]]);
            }
        }
        if (getenv("APP_ENV") === "test"
            && getenv("COUCHDB_URL") === "http://127.0.0.1:5984"
            && getenv("COUCHDB_DATABASE") === "vite_gourmand_stats_test") {
            require $argv[1] . "/backend/services/NoSqlStatistics.php";
            $statistics = new NoSqlStatistics();
            foreach ($orderIds as $orderId) $statistics->deleteOrder((int) $orderId);
        }
    }
      $pdo->prepare("DELETE FROM utilisateur WHERE utilisateur_id IN ($userMarks)")->execute($userIds);
    }
    $menuTitle = getenv("TEST_MENU_TITLE");
    $dishName = getenv("TEST_DISH_NAME");
    $menuQuery = $pdo->prepare("SELECT menu_id FROM menu WHERE titre = ?"); $menuQuery->execute([$menuTitle]); $menuIds = $menuQuery->fetchAll(PDO::FETCH_COLUMN);
    $dishQuery = $pdo->prepare("SELECT plat_id FROM plat WHERE nom = ?"); $dishQuery->execute([$dishName]); $dishIds = $dishQuery->fetchAll(PDO::FETCH_COLUMN);
    if ($menuIds) { $marks=implode(",",array_fill(0,count($menuIds),"?")); $pdo->prepare("DELETE FROM image_menu WHERE menu_id IN ($marks)")->execute($menuIds); $pdo->prepare("DELETE FROM menu_plat WHERE menu_id IN ($marks)")->execute($menuIds); $pdo->prepare("DELETE FROM menu WHERE menu_id IN ($marks)")->execute($menuIds); }
    if ($dishIds) { $marks=implode(",",array_fill(0,count($dishIds),"?")); $pdo->prepare("DELETE FROM plat_allergene WHERE plat_id IN ($marks)")->execute($dishIds); $pdo->prepare("DELETE FROM menu_plat WHERE plat_id IN ($marks)")->execute($dishIds); $pdo->prepare("DELETE FROM plat WHERE plat_id IN ($marks)")->execute($dishIds); }
    $pdo->prepare("DELETE FROM horaire WHERE jour = ?")->execute([getenv("TEST_DAY")]);
  ' "$project_root"
}

cleanup() {
  if [[ -n "$server_pid" ]]; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  cleanup_database
  rm -f "$client_cookie" "$staff_cookie" "$server_log" "$mail_log"
}
trap cleanup EXIT

request() {
  local expected=$1
  shift
  local output http_status body
  output=$(curl --silent --show-error --write-out $'\n%{http_code}' "$@")
  http_status=${output##*$'\n'}
  body=${output%$'\n'*}
  if [[ "$http_status" != "$expected" ]]; then
    echo "ÉCHEC HTTP: attendu=$expected obtenu=$http_status réponse=$body" >&2
    return 1
  fi
  printf '%s' "$body"
}

verify_database
cleanup_database

(
  cd "$project_root"
  APP_ENV=test APP_BASE_URL="http://127.0.0.1:${port}/frontend" DELIVERY_TEST_DISTANCE_KM=10 MAIL_TRANSPORT=log MAIL_LOG_PATH="$mail_log" CONTACT_EMAIL='contact@example.test' \
    php -S "127.0.0.1:${port}" >"$server_log" 2>&1
) &
server_pid=$!

for _ in {1..50}; do
  if curl --silent --fail "${base_url}/menu.php" >/dev/null; then
    break
  fi
  sleep 0.1
done
curl --silent --fail "${base_url}/menu.php" >/dev/null

request 405 --request POST "${base_url}/menu.php" >/dev/null
request 200 "${base_url}/menu.php" \
  | jq --exit-status 'any(.[]; .menu_id == 1 and (.plats | any(.nom == "Saumon fumé" and (.allergenes | any(.nom == "Poisson")))))' >/dev/null
request 200 "${base_url}/horaires.php" | jq --exit-status '.horaires | length >= 5' >/dev/null
request 200 "${base_url}/avis_publics.php" | jq --exit-status '.avis | type == "array"' >/dev/null
request 415 --request POST "${base_url}/inscription.php" >/dev/null
request 422 --header 'Content-Type: application/json' \
  --data '{"nom":"A","prenom":"B","email":"invalide","mot_de_passe":"faible"}' \
  "${base_url}/inscription.php" >/dev/null
request 422 --header 'Content-Type: application/json' \
  --data '{"email":"invalide","titre":"x","description":"trop court"}' \
  "${base_url}/contact.php" >/dev/null
request 202 --header 'Content-Type: application/json' \
  --data '{"email":"visiteur@example.test","titre":"Demande de devis","description":"Bonjour, je souhaite organiser un événement à Bordeaux."}' \
  "${base_url}/contact.php" >/dev/null
jq --exit-status 'select(.to == "contact@example.test" and .subject == "[Contact] Demande de devis")' "$mail_log" >/dev/null

request 201 --header 'Content-Type: application/json' \
  --data "{\"nom\":\"Codex\",\"prenom\":\"Client\",\"email\":\"${client_email}\",\"telephone\":\"0600000001\",\"adresse\":\"1 rue Test, Bordeaux\",\"mot_de_passe\":\"${client_password}\"}" \
  "${base_url}/inscription.php" >/dev/null
request 409 --header 'Content-Type: application/json' \
  --data "{\"nom\":\"Codex\",\"prenom\":\"Client\",\"email\":\"${client_email}\",\"telephone\":\"0600000001\",\"adresse\":\"1 rue Test, Bordeaux\",\"mot_de_passe\":\"${client_password}\"}" \
  "${base_url}/inscription.php" >/dev/null
request 401 --header 'Content-Type: application/json' \
  --data "{\"email\":\"${client_email}\",\"mot_de_passe\":\"Incorrect!Passw0rd2026\"}" \
  "${base_url}/connexion.php" >/dev/null

client_login=$(request 200 --cookie-jar "$client_cookie" --cookie "$client_cookie" \
  --header 'Content-Type: application/json' \
  --data "{\"email\":\"${client_email}\",\"mot_de_passe\":\"${client_password}\"}" \
  "${base_url}/connexion.php")
client_token=$(jq --raw-output '.csrf_token' <<<"$client_login")
request 200 --cookie "$client_cookie" "${base_url}/session_courante.php" >/dev/null
request 200 --cookie "$client_cookie" "${base_url}/profil.php" | jq --exit-status --arg email "$client_email" '.profil.email == $email' >/dev/null
request 200 --cookie "$client_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${client_token}" \
  --data "{\"nom\":\"Codex\",\"prenom\":\"Client\",\"email\":\"${client_email}\",\"telephone\":\"0600000001\",\"adresse\":\"1 rue Test, Bordeaux\"}" \
  "${base_url}/profil.php" >/dev/null

order=$(request 201 --cookie "$client_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${client_token}" \
  --data "{\"menu_id\":2,\"nombre_personnes\":10,\"date_prestation\":\"${order_date}\",\"heure_livraison\":\"12:00\",\"lieu_livraison\":\"1 rue Test, Bordeaux\",\"ville_livraison\":\"Bordeaux\"}" \
  "${base_url}/creer_commande.php")
order_id=$(jq --raw-output '.commande_id' <<<"$order")
jq --exit-status '.prix_total == "450.00"' <<<"$order" >/dev/null
request 200 --cookie "$client_cookie" "${base_url}/mes_commandes.php" \
  | jq --exit-status --argjson id "$order_id" '.commandes | any(.commande_id == $id)' >/dev/null
request 200 --cookie "$client_cookie" "${base_url}/suivi_commande.php?commande_id=${order_id}" \
  | jq --exit-status '.historique | length == 1' >/dev/null

request 200 --cookie "$client_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${client_token}" \
  --data "{\"commande_id\":${order_id},\"nombre_personnes\":5,\"date_prestation\":\"${modified_order_date}\",\"heure_livraison\":\"13:00\",\"lieu_livraison\":\"2 rue Test, Bordeaux\",\"ville_livraison\":\"Bordeaux\"}" \
  "${base_url}/modifier_commande.php" | jq --exit-status '.prix_total == "250.00"' >/dev/null

outside_order=$(request 201 --cookie "$client_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${client_token}" \
  --data "{\"menu_id\":2,\"nombre_personnes\":5,\"date_prestation\":\"${modified_order_date}\",\"heure_livraison\":\"14:00\",\"lieu_livraison\":\"1 avenue Test\",\"ville_livraison\":\"Mérignac\"}" \
  "${base_url}/creer_commande.php")
outside_order_id=$(jq --raw-output '.commande_id' <<<"$outside_order")
jq --exit-status '.distance_km == "10.00" and .frais_livraison == "10.90" and .prix_total == "260.90"' <<<"$outside_order" >/dev/null
request 200 --cookie "$client_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${client_token}" --data "{\"commande_id\":${outside_order_id}}" \
  "${base_url}/annuler_commande.php" >/dev/null

stock_before=$(request 200 "${base_url}/menu.php" \
  | jq --raw-output '.[] | select(.menu_id == 2) | .stock')
cancel_order=$(request 201 --cookie "$client_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${client_token}" \
  --data "{\"menu_id\":2,\"nombre_personnes\":5,\"date_prestation\":\"${cancel_order_date}\",\"heure_livraison\":\"12:00\",\"lieu_livraison\":\"3 rue Test, Bordeaux\",\"ville_livraison\":\"Bordeaux\"}" \
  "${base_url}/creer_commande.php")
cancel_order_id=$(jq --raw-output '.commande_id' <<<"$cancel_order")
request 200 --cookie "$client_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${client_token}" --data "{\"commande_id\":${cancel_order_id}}" \
  "${base_url}/annuler_commande.php" >/dev/null
request 200 --cookie "$client_cookie" "${base_url}/suivi_commande.php?commande_id=${cancel_order_id}" \
  | jq --exit-status '.commande.statut == "annulée" and (.historique | length == 2)' >/dev/null
stock_after=$(request 200 "${base_url}/menu.php" \
  | jq --raw-output '.[] | select(.menu_id == 2) | .stock')
[[ "$stock_after" == "$stock_before" ]]

request 201 --header 'Content-Type: application/json' \
  --data "{\"nom\":\"Codex\",\"prenom\":\"Staff\",\"email\":\"${staff_email}\",\"telephone\":\"0600000002\",\"adresse\":\"2 rue Test, Bordeaux\",\"mot_de_passe\":\"${staff_password}\"}" \
  "${base_url}/inscription.php" >/dev/null
TEST_STAFF_EMAIL="$staff_email" php -r '
  require $argv[1] . "/backend/config/database.php";
  if ($pdo->query("SELECT DATABASE()")->fetchColumn() !== "vite_gourmand_test") exit(42);
  $query = $pdo->prepare("UPDATE utilisateur SET role = ? WHERE email = ?");
  $query->execute(["employe", getenv("TEST_STAFF_EMAIL")]);
' "$project_root"

staff_login=$(request 200 --cookie-jar "$staff_cookie" --cookie "$staff_cookie" \
  --header 'Content-Type: application/json' \
  --data "{\"email\":\"${staff_email}\",\"mot_de_passe\":\"${staff_password}\"}" \
  "${base_url}/connexion.php")
staff_token=$(jq --raw-output '.csrf_token' <<<"$staff_login")
staff_cancel_order=$(request 201 --cookie "$client_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${client_token}" \
  --data "{\"menu_id\":2,\"nombre_personnes\":5,\"date_prestation\":\"${cancel_order_date}\",\"heure_livraison\":\"15:00\",\"lieu_livraison\":\"4 rue Test, Bordeaux\",\"ville_livraison\":\"Bordeaux\"}" "${base_url}/creer_commande.php")
staff_cancel_order_id=$(jq --raw-output '.commande_id' <<<"$staff_cancel_order")
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"action\":\"modifier\",\"commande_id\":${staff_cancel_order_id},\"nombre_personnes\":10,\"date_prestation\":\"${cancel_order_date}\",\"heure_livraison\":\"16:00\",\"lieu_livraison\":\"5 avenue Test\",\"ville_livraison\":\"Mérignac\",\"mode_contact\":\"telephone\",\"motif\":\"Le client a confirmé les nouvelles informations.\"}" "${base_url}/intervention_commande.php" \
  | jq --exit-status '.prix_total == "460.90" and .frais_livraison == "10.90"' >/dev/null
request 200 --cookie "$staff_cookie" "${base_url}/commandes_employe.php?client=Codex" \
  | jq --exit-status --argjson id "$staff_cancel_order_id" '.commandes | any(.commande_id == $id and .ville_livraison == "Mérignac" and .nombre_personnes == 10)' >/dev/null
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"action\":\"annuler\",\"commande_id\":${staff_cancel_order_id},\"mode_contact\":\"email\",\"motif\":\"Le client a confirmé par email son souhait d’annuler.\"}" "${base_url}/intervention_commande.php" >/dev/null
request 200 --cookie "$client_cookie" "${base_url}/suivi_commande.php?commande_id=${staff_cancel_order_id}" \
  | jq --exit-status '.commande.statut == "annulée" and (.historique | length == 2)' >/dev/null
request 200 --cookie "$staff_cookie" "${base_url}/commandes_employe.php?client=Codex" \
  | jq --exit-status --argjson id "$order_id" '.commandes | any(.commande_id == $id)' >/dev/null
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"commande_id\":${order_id},\"statut\":\"accepté\"}" \
  "${base_url}/statut_commande.php" >/dev/null
request 409 --cookie "$client_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${client_token}" --data "{\"commande_id\":${order_id}}" \
  "${base_url}/annuler_commande.php" >/dev/null
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"commande_id\":${order_id},\"statut\":\"en préparation\"}" \
  "${base_url}/statut_commande.php" >/dev/null
request 409 --cookie "$staff_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"commande_id\":${order_id},\"statut\":\"livré\"}" \
  "${base_url}/statut_commande.php" >/dev/null
for next_status in 'en cours de livraison' 'livré' 'terminée'; do
  request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' \
    --header "X-CSRF-Token: ${staff_token}" \
    --data "{\"commande_id\":${order_id},\"statut\":\"${next_status}\"}" \
    "${base_url}/statut_commande.php" >/dev/null
done
review=$(request 201 --cookie "$client_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${client_token}" \
  --data "{\"commande_id\":${order_id},\"note\":5,\"commentaire\":\"Service de test excellent et ponctuel.\"}" \
  "${base_url}/avis_client.php")
review_id=$(jq --raw-output '.avis_id' <<<"$review")
request 409 --cookie "$client_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${client_token}" \
  --data "{\"commande_id\":${order_id},\"note\":4,\"commentaire\":\"Avis en double refusé.\"}" \
  "${base_url}/avis_client.php" >/dev/null
request 200 --cookie "$staff_cookie" "${base_url}/avis_employe.php" \
  | jq --exit-status --argjson id "$review_id" '.avis | any(.avis_id == $id and .statut == "en attente")' >/dev/null
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"avis_id\":${review_id},\"statut\":\"validé\"}" "${base_url}/avis_employe.php" >/dev/null
request 200 "${base_url}/avis_publics.php" \
  | jq --exit-status --argjson id "$review_id" '.avis | any(.avis_id == $id and .note == 5)' >/dev/null

TEST_STAFF_EMAIL="$staff_email" php -r '
  require $argv[1] . "/backend/config/database.php";
  if ($pdo->query("SELECT DATABASE()")->fetchColumn() !== "vite_gourmand_test") exit(42);
  $query = $pdo->prepare("UPDATE utilisateur SET role = ? WHERE email = ?");
  $query->execute(["administrateur", getenv("TEST_STAFF_EMAIL")]);
' "$project_root"
request 200 --cookie "$staff_cookie" "${base_url}/session_courante.php" | jq --exit-status '.utilisateur.role == "administrateur"' >/dev/null
if [[ "${COUCHDB_URL:-}" == "http://127.0.0.1:5984" && "${COUCHDB_DATABASE:-}" == "vite_gourmand_stats_test" && "${APP_ENV:-}" == "test" ]]; then
  request 200 --cookie "$staff_cookie" "${base_url}/statistiques_admin.php?menu_id=2" \
    | jq --exit-status '.source == "couchdb" and .nombre_commandes >= 1 and (.par_menu | any(.menu_id == 2))' >/dev/null
else
  request 503 --cookie "$staff_cookie" "${base_url}/statistiques_admin.php?menu_id=2" \
    | jq --exit-status '.erreur == "Statistiques NoSQL indisponibles."' >/dev/null
fi
request 422 --cookie "$staff_cookie" "${base_url}/statistiques_admin.php?date_debut=incorrecte" >/dev/null
managed=$(request 201 --cookie "$staff_cookie" --header 'Content-Type: application/json' \
  --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"action\":\"creer\",\"nom\":\"Géré\",\"prenom\":\"Compte\",\"email\":\"${managed_email}\",\"mot_de_passe\":\"Managed!Passw0rd2026\"}" \
  "${base_url}/employes_admin.php")
managed_id=$(jq --raw-output '.utilisateur_id' <<<"$managed")
request 200 --cookie "$staff_cookie" "${base_url}/employes_admin.php" \
  | jq --exit-status --argjson id "$managed_id" '.employes | any(.utilisateur_id == $id and .actif == 1)' >/dev/null
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"action\":\"activer\",\"utilisateur_id\":${managed_id},\"actif\":false}" "${base_url}/employes_admin.php" >/dev/null

dish=$(request 201 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"action\":\"sauvegarder_plat\",\"nom\":\"${test_dish_name}\",\"type_plat\":\"plat\",\"description\":\"Plat temporaire de recette\",\"allergene_ids\":[1]}" "${base_url}/catalogue_employe.php")
dish_id=$(jq --raw-output '.plat_id' <<<"$dish")
menu_created=$(request 201 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"action\":\"sauvegarder_menu\",\"titre\":\"${test_menu_title}\",\"description\":\"Menu temporaire de recette\",\"theme\":\"test\",\"regime\":\"classique\",\"nombre_personnes_min\":4,\"prix\":100,\"conditions_menu\":\"Commander deux jours avant\",\"stock\":2,\"disponible\":true}" "${base_url}/catalogue_employe.php")
menu_id=$(jq --raw-output '.menu_id' <<<"$menu_created")
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"action\":\"sauvegarder_menu\",\"menu_id\":${menu_id},\"titre\":\"${test_menu_title}\",\"description\":\"Menu temporaire modifié\",\"theme\":\"test\",\"regime\":\"classique\",\"nombre_personnes_min\":5,\"prix\":125,\"conditions_menu\":\"Commander deux jours avant\",\"stock\":3,\"disponible\":true}" "${base_url}/catalogue_employe.php" >/dev/null
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"action\":\"associer_plats\",\"menu_id\":${menu_id},\"plat_ids\":[${dish_id}]}" "${base_url}/catalogue_employe.php" >/dev/null
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"action\":\"sauvegarder_images\",\"menu_id\":${menu_id},\"chemins\":[\"assets/test-menu.webp\"]}" "${base_url}/catalogue_employe.php" >/dev/null
request 200 "${base_url}/menu.php" | jq --exit-status --argjson id "$menu_id" --arg dish "$test_dish_name" 'any(.[]; .menu_id == $id and .prix == "125.00" and (.images | any(.chemin_image == "assets/test-menu.webp")) and (.plats | any(.nom == $dish and (.allergenes | any(.nom == "Gluten")))))' >/dev/null
hour=$(request 201 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" \
  --data "{\"jour\":\"${test_day}\",\"heure_ouverture\":\"10:00\",\"heure_fermeture\":\"16:00\"}" "${base_url}/horaires_employe.php")
hour_id=$(jq --raw-output '.horaire_id' <<<"$hour")
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" --data "{\"horaire_id\":${hour_id},\"jour\":\"${test_day}\",\"heure_ouverture\":\"11:00\",\"heure_fermeture\":\"17:00\"}" "${base_url}/horaires_employe.php" >/dev/null
request 200 "${base_url}/horaires.php" | jq --exit-status --arg day "$test_day" '.horaires | any(.jour == $day and .heure_ouverture == "11:00")' >/dev/null
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" --data "{\"action\":\"supprimer\",\"horaire_id\":${hour_id}}" "${base_url}/horaires_employe.php" >/dev/null
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" --data "{\"action\":\"supprimer_menu\",\"menu_id\":${menu_id}}" "${base_url}/catalogue_employe.php" >/dev/null
request 200 --cookie "$staff_cookie" --header 'Content-Type: application/json' --header "X-CSRF-Token: ${staff_token}" --data "{\"action\":\"supprimer_plat\",\"plat_id\":${dish_id}}" "${base_url}/catalogue_employe.php" >/dev/null

request 202 --header 'Content-Type: application/json' \
  --data "{\"email\":\"${client_email}\"}" "${base_url}/demander_reinitialisation.php" >/dev/null
reset_token=$(jq --raw-output 'select(.subject == "Réinitialisation de votre mot de passe") | .text' "$mail_log" \
  | grep -Eo 'token=[a-f0-9]{64}' | tail -1 | cut -d= -f2)
[[ ${#reset_token} -eq 64 ]]
request 200 --header 'Content-Type: application/json' \
  --data "{\"token\":\"${reset_token}\",\"mot_de_passe\":\"${new_client_password}\"}" \
  "${base_url}/reinitialiser_mot_de_passe.php" >/dev/null
request 410 --header 'Content-Type: application/json' \
  --data "{\"token\":\"${reset_token}\",\"mot_de_passe\":\"${client_password}\"}" \
  "${base_url}/reinitialiser_mot_de_passe.php" >/dev/null

request 200 --request POST --cookie "$client_cookie" --header "X-CSRF-Token: ${client_token}" \
  "${base_url}/deconnexion.php" >/dev/null
request 401 --cookie "$client_cookie" "${base_url}/session_courante.php" >/dev/null
request 401 --header 'Content-Type: application/json' \
  --data "{\"email\":\"${client_email}\",\"mot_de_passe\":\"${client_password}\"}" "${base_url}/connexion.php" >/dev/null
request 200 --header 'Content-Type: application/json' \
  --data "{\"email\":\"${client_email}\",\"mot_de_passe\":\"${new_client_password}\"}" "${base_url}/connexion.php" >/dev/null

echo "PASS: scénario API complet sur vite_gourmand_test (commande temporaire ${order_id}, nettoyée à la sortie)."

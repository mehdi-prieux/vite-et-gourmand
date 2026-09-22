<?php

declare(strict_types=1);

/** Calcule une distance routière vérifiable et les frais associés. */
function calculateDelivery(string $address, string $city): array
{
    if (strtolower(trim($city)) === 'bordeaux') {
        return ['distance_km' => 0.0, 'fee' => 0.0];
    }

    if (getenv('APP_ENV') === 'test' && getenv('DELIVERY_TEST_DISTANCE_KM') !== false) {
        $distance = filter_var(getenv('DELIVERY_TEST_DISTANCE_KM'), FILTER_VALIDATE_FLOAT);
        if ($distance === false || $distance <= 0) throw new RuntimeException('Distance de test invalide.');
        return ['distance_km' => round($distance, 2), 'fee' => round(5 + 0.59 * $distance, 2)];
    }

    $query = rawurlencode(trim($address) . ', ' . trim($city) . ', France');
    $geocoder = getenv('GEOCODING_ENDPOINT') ?: 'https://nominatim.openstreetmap.org/search';
    $geocoded = deliveryHttpJson($geocoder . '?format=jsonv2&limit=1&q=' . $query);
    if (!is_array($geocoded) || !isset($geocoded[0]['lon'], $geocoded[0]['lat'])) {
        throw new DomainException('Adresse de livraison introuvable.');
    }
    $lon = filter_var($geocoded[0]['lon'], FILTER_VALIDATE_FLOAT);
    $lat = filter_var($geocoded[0]['lat'], FILTER_VALIDATE_FLOAT);
    if ($lon === false || $lat === false) throw new DomainException('Coordonnées de livraison invalides.');

    // Siège de Bordeaux, utilisé comme origine du trajet.
    $originLon = -0.57918;
    $originLat = 44.83779;
    $router = getenv('ROUTING_ENDPOINT') ?: 'https://router.project-osrm.org/route/v1/driving';
    $route = deliveryHttpJson($router . "/{$originLon},{$originLat};{$lon},{$lat}?overview=false");
    $meters = $route['routes'][0]['distance'] ?? null;
    if (!is_numeric($meters) || $meters <= 0) throw new DomainException('Itinéraire de livraison indisponible.');
    $distance = round(((float) $meters) / 1000, 2);
    return ['distance_km' => $distance, 'fee' => round(5 + 0.59 * $distance, 2)];
}

function deliveryHttpJson(string $url): array
{
    $curl = curl_init($url);
    if ($curl === false) throw new RuntimeException('Service HTTP indisponible.');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'ViteGourmand/1.0 (contact form)',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if (!is_string($body) || $status < 200 || $status >= 300) throw new RuntimeException('Service de distance indisponible : ' . $error);
    $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Réponse du service de distance invalide.');
    return $data;
}

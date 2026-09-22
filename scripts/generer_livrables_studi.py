#!/usr/bin/env python3
"""Génère les deux PDF explicitement demandés par l'énoncé Studi."""

from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.platypus import PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "output" / "pdf"
OUTPUT.mkdir(parents=True, exist_ok=True)
FONT = "/System/Library/Fonts/Supplemental/Arial.ttf"
if Path(FONT).exists():
    pdfmetrics.registerFont(TTFont("ArialStudi", FONT))
    FAMILY = "ArialStudi"
else:
    FAMILY = "Helvetica"

INK = colors.HexColor("#17362A")
BRAND = colors.HexColor("#1F6545")
DARK = colors.HexColor("#14442F")
ACCENT = colors.HexColor("#D49A3A")
SOFT = colors.HexColor("#EEF4EC")
PAPER = colors.HexColor("#FFFDF8")
MUTED = colors.HexColor("#52665D")
BORDER = colors.HexColor("#CDD9D0")


def footer(canv, doc):
    canv.saveState()
    canv.setStrokeColor(BORDER)
    canv.line(19 * mm, 18 * mm, 191 * mm, 18 * mm)
    canv.setFont(FAMILY, 8)
    canv.setFillColor(MUTED)
    canv.drawString(19 * mm, 13 * mm, "Vite & Gourmand - Dossier Studi - septembre 2026")
    canv.drawRightString(191 * mm, 13 * mm, str(doc.page))
    canv.restoreState()


def manual():
    path = OUTPUT / "manuel_utilisateur_studi.pdf"
    styles = getSampleStyleSheet()
    styles.add(ParagraphStyle(name="TitleVG", fontName=FAMILY, fontSize=23, leading=28, textColor=INK, spaceAfter=12))
    styles.add(ParagraphStyle(name="HeadVG", fontName=FAMILY, fontSize=14, leading=18, textColor=BRAND, spaceBefore=14, spaceAfter=7))
    styles.add(ParagraphStyle(name="BodyVG", fontName=FAMILY, fontSize=10, leading=15, textColor=INK, spaceAfter=7))
    styles.add(ParagraphStyle(name="SmallVG", fontName=FAMILY, fontSize=8.5, leading=12, textColor=MUTED, spaceAfter=5))
    styles.add(ParagraphStyle(name="CenterVG", parent=styles["BodyVG"], alignment=TA_CENTER))
    doc = SimpleDocTemplate(str(path), pagesize=A4, leftMargin=19 * mm, rightMargin=19 * mm, topMargin=23 * mm, bottomMargin=24 * mm)
    story = [
        Paragraph("Manuel d'utilisation", styles["TitleVG"]),
        Paragraph("Vite &amp; Gourmand - Application de traiteur événementiel", styles["BodyVG"]),
        Paragraph("Version du 22 septembre 2026. Ce manuel décrit l'instance locale de démonstration ; les comptes ci-dessous sont publics et réservés aux tests.", styles["SmallVG"]),
        Spacer(1, 8 * mm),
        Paragraph("Accéder à l'application", styles["HeadVG"]),
        Paragraph("Démarrer le serveur PHP à la racine du projet puis ouvrir <b>http://127.0.0.1:8000/frontend/</b>. La page d'accueil présente l'entreprise, les menus du moment et les avis validés. La barre de navigation mène aux menus, à l'espace personnel et au contact.", styles["BodyVG"]),
        Paragraph("Comptes de démonstration", styles["HeadVG"]),
    ]
    credentials = [
        ["Rôle", "Adresse e-mail", "Mot de passe"],
        ["Client", "client@test.com", "Client!Demo2026"],
        ["Employé", "employee@test.com", "Employee!Demo2026"],
        ["Administrateur", "admin@test.com", "Admin!Demo2026"],
    ]
    table = Table(credentials, colWidths=[33 * mm, 68 * mm, 64 * mm], repeatRows=1)
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), BRAND), ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("FONTNAME", (0, 0), (-1, -1), FAMILY), ("FONTSIZE", (0, 0), (-1, -1), 8.5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7), ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, SOFT]),
        ("GRID", (0, 0), (-1, -1), .4, BORDER),
    ]))
    story += [table, Paragraph("Créer un nouveau compte", styles["HeadVG"]), Paragraph("Depuis l'espace client, cliquer sur <b>Créer un compte</b>. Renseigner nom, prénom, téléphone, adresse postale, e-mail et mot de passe. Le mot de passe comporte au moins 12 caractères, avec majuscule, minuscule, chiffre et caractère spécial. Un e-mail de bienvenue est envoyé.", styles["BodyVG"])]

    sections = [
        ("Parcours visiteur", [
            "Ouvrir <b>Menus</b>, puis ajuster la fourchette de prix, le thème, le régime ou le nombre de personnes. Les résultats se mettent à jour sans recharger la page.",
            "Choisir <b>Voir le détail</b> pour consulter description, composition, allergènes, images, stock et conditions. Le bouton <b>Commander ce menu</b> conserve le menu choisi ; une connexion est demandée si nécessaire.",
            "Utiliser <b>Contact</b> pour transmettre un titre, une description et une adresse e-mail. Les horaires, mentions légales et CGV sont accessibles depuis le pied de page.",
        ]),
        ("Parcours client", [
            "Se connecter depuis <b>Mon espace</b>. Vérifier ses coordonnées dans <b>Mes informations</b>. En cas d'oubli du mot de passe, demander un lien de réinitialisation par e-mail.",
            "Dans <b>Passer une commande</b>, choisir le menu, le nombre de convives, la date, l'heure et l'adresse. Le nombre de personnes respecte le minimum du menu. Le prix du menu est estimé avant l'envoi ; hors Bordeaux, les frais sont calculés par le serveur selon la distance routière (5 EUR + 0,59 EUR/km). À partir de cinq convives supplémentaires, une remise de 10 % s'applique.",
            "Après l'envoi, consulter <b>Mes commandes</b> et <b>Voir le suivi</b>. Tant que la commande est en attente, elle peut être modifiée ou annulée ; le menu ne peut pas être changé. Après acceptation, suivre les étapes jusqu'à la fin. Une commande terminée permet de donner une note de 1 à 5 et un commentaire, soumis à modération.",
        ]),
        ("Parcours employé", [
            "Se connecter dans l'<b>Espace personnel</b>. Filtrer les commandes par statut ou client, puis faire progresser leur statut selon l'avancement réel.",
            "Pour modifier ou annuler une commande, contacter d'abord le client par téléphone ou e-mail, sélectionner ce mode et saisir un motif. L'intervention est enregistrée.",
            "Dans <b>Gérer le catalogue</b>, créer ou modifier menus, plats, allergènes, compositions, galeries et horaires. Dans <b>Modérer les avis</b>, valider ou refuser les avis reçus. Le passage en attente du retour de matériel déclenche un e-mail rappelant le délai de dix jours ouvrés et les frais éventuels de 600 EUR.",
        ]),
        ("Parcours administrateur", [
            "Ouvrir <b>Administration</b>. Créer un employé avec un mot de passe initial, puis lui communiquer ce mot de passe hors e-mail applicatif. Activer ou désactiver son compte selon les besoins.",
            "Consulter le graphique de commandes par menu et le chiffre d'affaires. Les filtres portent sur le menu et la période. L'administrateur dispose aussi des fonctions de l'employé.",
        ]),
    ]
    for title, paragraphs in sections:
        if title == "Parcours client": story.append(PageBreak())
        story.append(Paragraph(title, styles["HeadVG"]))
        for item in paragraphs: story.append(Paragraph(item, styles["BodyVG"]))
    story += [Paragraph("Limites de l'instance de démonstration", styles["HeadVG"]), Paragraph("L'envoi d'e-mails et les statistiques documentaires nécessitent leur configuration serveur. Les comptes de démonstration doivent être remplacés avant une mise en ligne. Le site local ne constitue pas une preuve de déploiement public.", styles["BodyVG"])]
    doc.build(story, onFirstPage=footer, onLaterPages=footer)
    return path


def paragraph(canv, value, x, y, width, size=9, color=INK, leading=None):
    style = ParagraphStyle("box", fontName=FAMILY, fontSize=size, leading=leading or size * 1.35, textColor=color, alignment=TA_LEFT)
    element = Paragraph(value, style)
    _, height = element.wrap(width, 1000)
    element.drawOn(canv, x, y - height)
    return y - height


def pill(canv, x, y, w, h, label, fill=BRAND, foreground=colors.white):
    canv.setFillColor(fill)
    canv.roundRect(x, y, w, h, 8, stroke=0, fill=1)
    canv.setFillColor(foreground)
    canv.setFont(FAMILY, 8)
    canv.drawCentredString(x + w / 2, y + h / 2 - 3, label)


def frame(canv, x, y, w, h, title, wire=False):
    canv.setFillColor(colors.white if wire else PAPER)
    canv.setStrokeColor(BORDER)
    canv.roundRect(x, y, w, h, 11, stroke=1, fill=1)
    canv.setFillColor(colors.HexColor("#E6E6E6") if wire else INK)
    canv.roundRect(x, y + h - 36, w, 36, 11, stroke=0, fill=1)
    canv.setFillColor(INK if wire else colors.white)
    canv.setFont(FAMILY, 10)
    canv.drawString(x + 13, y + h - 22, "Vite & Gourmand")
    canv.setFont(FAMILY, 7)
    canv.drawRightString(x + w - 13, y + h - 21, title)


def block(canv, x, y, w, h, label, wire=False, accent=False):
    canv.setFillColor(colors.HexColor("#E8E8E8") if wire else (ACCENT if accent else SOFT))
    canv.setStrokeColor(BORDER)
    canv.roundRect(x, y, w, h, 6, stroke=1, fill=1)
    canv.setFillColor(INK)
    canv.setFont(FAMILY, 8)
    canv.drawCentredString(x + w / 2, y + h / 2 - 3, label)


def desktop(canv, x, y, w, h, name, wire):
    frame(canv, x, y, w, h, name, wire)
    top = y + h - 54
    if name == "Accueil":
        block(canv, x + 12, top - 108, w * .59, 105, "Héros : présentation + action", wire)
        block(canv, x + w * .63, top - 108, w * .33, 105, "25 ans / engagements", wire, True)
        for index in range(3): block(canv, x + 12 + index * (w - 32) / 3, top - 187, (w - 42) / 3, 65, "Savoir-faire", wire)
        for index in range(2): block(canv, x + 12 + index * (w - 30) / 2, top - 264, (w - 40) / 2, 63, "Menu du moment", wire)
        block(canv, x + 12, y + 12, w - 24, 46, "Avis validés + pied de page", wire)
    elif name == "Menus":
        block(canv, x + 12, top - 59, w - 24, 52, "Filtres : prix / thème / régime / convives", wire)
        for index in range(4):
            row, col = divmod(index, 2)
            block(canv, x + 12 + col * (w - 30) / 2, top - 165 - row * 102, (w - 40) / 2, 88, "Carte menu + prix + détail", wire)
        block(canv, x + 12, y + 12, w - 24, 35, "Horaires / mentions / CGV", wire)
    else:
        block(canv, x + 12, top - 52, w - 24, 45, "Compte et menu présélectionné", wire)
        block(canv, x + 12, top - 154, w * .55, 90, "Date / heure / lieu", wire)
        block(canv, x + w * .62, top - 154, w * .33, 90, "Convives", wire)
        block(canv, x + 12, top - 243, w - 24, 75, "Détail prix + livraison", wire, True)
        block(canv, x + 12, y + 12, w - 24, 44, "Valider la commande", wire)


def mobile(canv, x, y, w, h, name, wire):
    frame(canv, x, y, w, h, name, wire)
    top = y + h - 49
    if name == "Accueil":
        block(canv, x + 10, top - 106, w - 20, 98, "Présentation", wire)
        block(canv, x + 10, top - 166, w - 20, 48, "Découvrir les menus", wire, True)
        block(canv, x + 10, top - 246, w - 20, 69, "Savoir-faire", wire)
        block(canv, x + 10, y + 53, w - 20, 82, "Menus du moment", wire)
        block(canv, x + 10, y + 10, w - 20, 32, "Avis / pied de page", wire)
    elif name == "Menus":
        block(canv, x + 10, top - 55, w - 20, 48, "Filtres empilés", wire)
        for index in range(3): block(canv, x + 10, top - 139 - index * 87, w - 20, 75, "Menu + détail", wire)
        block(canv, x + 10, y + 10, w - 20, 36, "Horaires / CGV", wire)
    else:
        block(canv, x + 10, top - 58, w - 20, 50, "Compte + menu", wire)
        block(canv, x + 10, top - 167, w - 20, 98, "Date / heure / adresse", wire)
        block(canv, x + 10, top - 238, w - 20, 60, "Convives", wire)
        block(canv, x + 10, y + 55, w - 20, 78, "Prix + livraison", wire, True)
        block(canv, x + 10, y + 10, w - 20, 34, "Commander", wire)


def charter():
    path = OUTPUT / "charte_graphique_et_maquettes_studi.pdf"
    canv = canvas.Canvas(str(path), pagesize=landscape(A4))
    W, H = landscape(A4)
    canv.setFillColor(PAPER); canv.rect(0, 0, W, H, fill=1, stroke=0)
    canv.setFillColor(INK); canv.setFont(FAMILY, 27); canv.drawString(38, H - 72, "Charte graphique")
    canv.setFont(FAMILY, 13); canv.drawString(38, H - 99, "Vite & Gourmand - Identité visuelle et maquettes ECF")
    paragraph(canv, "Intention : une cuisine locale, accueillante et professionnelle. Le contraste vert foncé / fond clair facilite la lecture ; l'ocre sert aux accents et aux conditions importantes.", 38, H - 132, W - 76, 11)
    swatches = [(INK, "Encre #17362A"), (BRAND, "Vert #1F6545"), (DARK, "Vert foncé #14442F"), (ACCENT, "Ocre #D49A3A"), (SOFT, "Vert pâle #EEF4EC"), (PAPER, "Ivoire #FFFDF8")]
    for index, (color, label) in enumerate(swatches):
        x = 38 + index * 125
        canv.setFillColor(color); canv.setStrokeColor(BORDER); canv.roundRect(x, 205, 108, 108, 8, fill=1, stroke=1)
        canv.setFillColor(INK); canv.setFont(FAMILY, 9); canv.drawString(x, 188, label)
    paragraph(canv, "Typographie : Inter si disponible ; repli sur les polices système sans empattement. Titres gras, textes courants 16 px environ et interlignage 1,6. Boutons verts, focus très visible, étiquettes permanentes sur les champs.", 38, 158, W - 76, 11)
    paragraph(canv, "Les six maquettes suivantes couvrent les écrans Accueil, Menus et Commande en largeur bureau puis mobile. Chaque écran présente un wireframe gris et un mockup coloré. Elles représentent l'architecture et les composants du site, sans reproduire de données personnelles.", 38, 112, W - 76, 10)
    canv.setFont(FAMILY, 8); canv.drawRightString(W - 35, 24, "1 / 7")
    canv.showPage()
    pages = [("Bureau", n) for n in ["Accueil", "Menus", "Commande"]] + [("Mobile", n) for n in ["Accueil", "Menus", "Commande"]]
    for page_number, (kind, name) in enumerate(pages, start=2):
        canv.setFillColor(PAPER); canv.rect(0, 0, W, H, fill=1, stroke=0)
        canv.setFillColor(INK); canv.setFont(FAMILY, 20); canv.drawString(35, H - 48, f"{kind} - {name}")
        canv.setFont(FAMILY, 10); canv.setFillColor(MUTED)
        canv.drawString(35, H - 70, "Wireframe")
        canv.drawString(W / 2 + 8, H - 70, "Mockup")
        if kind == "Bureau":
            desktop(canv, 35, 73, W / 2 - 48, H - 160, name, True)
            desktop(canv, W / 2 + 8, 73, W / 2 - 43, H - 160, name, False)
        else:
            phone_width = 230
            mobile(canv, 113, 67, phone_width, H - 151, name, True)
            mobile(canv, W - 113 - phone_width, 67, phone_width, H - 151, name, False)
        canv.setFillColor(MUTED); canv.setFont(FAMILY, 8); canv.drawRightString(W - 35, 24, f"{page_number} / 7")
        canv.showPage()
    canv.save()
    return path


if __name__ == "__main__":
    for result in (manual(), charter()): print(result)

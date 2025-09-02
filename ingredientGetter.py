import requests

# Liste d'ingrédients à chercher
ingredients = [
    "poitrine de poulet",
    "entrecôte de boeuf",
    "filet de porc",
    "saumon",
    "moules",
    "jambon cru",
    "pommes de terre",
    "carottes",
    "oignons",
    "ail",
    "tomates",
    "courgettes",
    "salade",
    "citron",
    "pommes",
    "bananes",
    "oranges",
    "lait entier",
    "crème fraîche",
    "beurre",
    "fromage râpé",
    "pâtes",
    "riz",
    "farine",
    "sucre",
    "huile d'olive",
    "vinaigre balsamique",
    "tomates pelées",
    "olives",
    "moutarde de Dijon",
    "pain",
    "chocolat pâtissier",
    "oeufs"
]

# URL de recherche Open Food Facts
SEARCH_URL = "https://world.openfoodfacts.org/cgi/search.pl"

def get_barcode(ingredient):
    params = {
        "search_terms": ingredient,
        "search_simple": 1,
        "action": "process",
        "json": 1,
        "page_size": 1   # on récupère uniquement le premier résultat
    }
    response = requests.get(SEARCH_URL, params=params)
    data = response.json()

    products = data.get("products", [])
    if products:
        product = products[0]
        return {
            "ingredient": ingredient,
            "product_name": product.get("product_name", "N/A"),
            "barcode": product.get("code", "N/A")
        }
    else:
        return {
            "ingredient": ingredient,
            "product_name": "Aucun produit trouvé",
            "barcode": "N/A"
        }

# Boucle sur tous les ingrédients
results = []
for ingr in ingredients:
    results.append(get_barcode(ingr))

# Affichage
for r in results:
    print(f"{r['ingredient']} → {r['product_name']} (code-barres: {r['barcode']})")

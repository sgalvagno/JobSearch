# JobSearch

API personnelle de suivi d'offres et candidatures, réutilisable et auto-hébergeable. PHP 8.3+ sans framework, PDO, MariaDB 10.11+, Apache. Version initiale 0.1.0, sans interface graphique.

## Périmètre

- Entreprises, présentation originale et résumé distincts, contacts et provenance.
- Offres actives/périmées/fermées conservées dans la même table.
- Employeur et intermédiaire distincts, entreprises normales/à éviter/exclues.
- Favoris, notes, candidatures sur offre ou spontanées, événements et relances.
- Jetons opaques avec permissions et révocation ; import atomique et rejouable.
- Le dépôt applicatif ne contient ni offres réelles, ni sauvegardes, ni secrets. Le modèle d'import GitHub Actions est destiné à un dépôt de données privé séparé.

## Démarrage Docker

```sh
git clone https://github.com/sgalvagno/JobSearch.git
cd JobSearch
cp .env.example .env
# Modifier les deux mots de passe dans .env
docker compose up -d --build
# Garder le secret affiché hors du dépôt :
docker compose exec api php bin/token.php admin '*'
docker compose exec api php bin/token.php collector imports:write
```

API locale : `http://localhost:8080/api/v1`. Aucun port MariaDB n'est publié. HTTP est autorisé uniquement dans cette configuration locale ; ne pas la publier telle quelle.

```sh
export JOBSEARCH_TOKEN='SECRET_AFFICHE_PAR_LA_COMMANDE'
curl -H "Authorization: Bearer $JOBSEARCH_TOKEN" http://localhost:8080/api/v1/health
curl -H "Authorization: Bearer $JOBSEARCH_TOKEN" -H 'Content-Type: application/json' \
  --data-binary @examples/batch.json http://localhost:8080/api/v1/imports
curl -H "Authorization: Bearer $JOBSEARCH_TOKEN" 'http://localhost:8080/api/v1/offers?visibility=all'
```

L'exemple est fictif et n'est pas importé automatiquement. Ne pas modifier un batch déjà traité : créer un nouvel identifiant. Les scripts SQL d'initialisation Docker ne tournent que sur un volume vide. `docker compose down` conserve les données ; `docker compose down -v` les détruit.

## Installation IONOS

Voir [docs/INSTALL_IONOS.md](docs/INSTALL_IONOS.md). Le document root doit être `public/` : ne jamais exposer la racine du projet.

## Documentation

- [Contrat OpenAPI](openapi.yaml)
- [Modèle et règles métier](docs/MODEL.md)
- [Imports et relais GitHub privé](docs/IMPORTS.md)
- [Sécurité](SECURITY.md)
- [Contribuer](CONTRIBUTING.md)

## Statut et limites

Première version à valider en environnement local et sur l'hébergement cible. La CI livrée prévoit lint PHP, initialisation SQL et smoke tests HTTP ; sa présence ne signifie pas qu'elle a déjà réussi.

L'import V1 est volontairement **insert-only** : il ne met pas à jour les annonces existantes, ne fusionne pas automatiquement les annonces de sources différentes, n'enrichit pas les contacts, et ne propose pas de dry-run. Les modifications se font via les routes CRUD avec un jeton humain. Pas de collecte intégrée, scoring CV, emails sortants, authentification navigateur, multi-utilisateur, limitation de débit applicative ou sauvegarde automatique.

Licence : voir [LICENSE](LICENSE), conservée depuis le dépôt initial.

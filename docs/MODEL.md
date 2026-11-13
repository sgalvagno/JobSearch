# Modèle et décisions métier

Application personnelle mono-utilisateur : les jetons représentent des clients techniques, pas des comptes indépendants. Pour plusieurs utilisateurs, déployer des instances séparées ; aucune isolation multi-tenant n'est fournie.

## Tables

- `companies` : identité, adresse principale, activité, résumé IA et présentation originale distincts, provenance.
- `company_aliases` : variantes de noms. Prévue au SQL ; gestion API dédiée non encore implémentée.
- `company_contacts` : personnes ou contacts génériques, sources et date de vérification.
- `company_preferences` : choix humains uniquement. Type ESN indépendant de la visibilité.
- `job_offers` : contenu, localisation, contrat, rémunération et disponibilité ; employeur et recruteur facultatifs.
- `job_offer_sources` : source et identité externe. Plusieurs sources possibles en base ; la V1 d'import ne les rapproche pas sémantiquement.
- `job_offer_tracking` : favoris et notes humaines, hors données d'import.
- `applications` : candidature liée à une entreprise et facultativement une offre/contact.
- `application_events` : historique manuel. Changer le statut n'ajoute pas automatiquement un événement.
- `api_tokens` : empreinte des secrets et permissions.
- `import_runs` : résultat des lots réussis. Un échec atomique ne laisse pas de lot enregistré.

## Règles

Une offre périmée n'est pas supprimée. La suppression d'offre/contact est logique ; aucune suppression physique n'est exposée. Les entreprises et candidatures n'ont pas de route DELETE en V1.

Les dates des champs SQL sont des chaînes `YYYY-MM-DD HH:MM:SS`, en UTC. Les dates de disponibilité ne changent pas automatiquement : le collecteur ou l'utilisateur doit constater puis renseigner le statut. La disparition d'un résultat de recherche n'est pas une preuve de fermeture.

Les listes globales sont paginées (25 par défaut, 100 maximum). Les sous-listes entreprise et la liste des relances sont limitées à 100 éléments en V1. La liste globale des offres masque par défaut les entreprises à éviter/exclues, employeur **et** recruteur compris. `visibility=all` retire ce filtre. Une consultation directe par identifiant reste possible : la préférence n'est pas une restriction d'autorisation.

## Marquer une candidature effectuée

```http
POST /api/v1/applications
Content-Type: application/json
Authorization: Bearer TOKEN

{"company_id":1,"job_offer_id":1,"type":"job_offer","status":"sent","channel":"website","applied_at":"2026-11-13 17:00:00"}
```

Pour une candidature spontanée, utiliser `type=spontaneous` et ne pas renseigner `job_offer_id`. Le statut non brouillon exige `applied_at`. Une candidature ne modifie pas la disponibilité de l'offre.

## Évolutions prévues, non implémentées

Matching CV explicable, compétences normalisées, versions de CV, établissements multiples, historique des annonces, recherche plein texte, révision des rapprochements, imports d'enrichissement et protection champ par champ des corrections humaines. Ces sujets ne doivent pas être confondus avec les capacités réelles de la V1.

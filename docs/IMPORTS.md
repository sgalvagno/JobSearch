# Collecte et import

## Deux transports, un seul contrat

1. IA -> POST HTTPS `/api/v1/imports`.
2. IA -> JSON commité par elle ou manuellement dans un dépôt privé distinct -> GitHub Actions -> même endpoint.

Ce dépôt contient le code et un exemple fictif uniquement. Il ne sert ni d'archive des offres ni de sauvegarde MariaDB. Le dépôt de collecte privé est un relais de fichiers ; il ne remplace pas une sauvegarde de la base.

## Dépôt privé de collecte

Copier `docs/PRIVATE_IMPORT_WORKFLOW.yml.example` dans `.github/workflows/import.yml` de votre dépôt privé. Créer les secrets `JOBSEARCH_IMPORT_URL` (URL HTTPS complète) et `JOBSEARCH_IMPORT_TOKEN` (permission `imports:write`). Déposer un nouveau fichier par recherche dans `imports/recherches/`. Ne jamais commiter de jeton.

Le workflow proposé rejoue tous les fichiers du répertoire pour ne pas perdre de lots en cas de commits rapprochés ou de reprise. Il ne déplace pas les fichiers et ne committe pas de résultat. Pour un volume important, prévoir une vraie file/manifest plutôt que relire tous les lots. Exécuter uniquement du code approuvé sur la branche de confiance ; aucune exécution d'import sur pull_request.

## Contrat V1

Voir `examples/batch.json` et `openapi.yaml`. Maximum 2 MiB par requête et 200 entreprises/200 offres. `batch_id` unique globalement, immuable. Conserver le fichier identique pour un rejeu : le hash est calculé sur le JSON parsé puis réencodé et l'ordre des propriétés reste significatif.

Les entreprises ont une `ref` locale au lot et les offres peuvent avoir un `company_ref`. L'importeur ne fournit pas d'identifiants internes entreprise/recruteur. Une entreprise est réutilisée si son SIREN existe déjà ; sinon elle est créée sans fusion sur nom ou domaine. Des doublons d'entreprises restent donc possibles et nécessitent une revue humaine.

Pour chaque offre, `source.source_name` et `source.source_url` sont obligatoires ; `external_id` est recommandé. Correspondance sur hash d'URL exacte après trim ou paire source/identifiant externe : offre ignorée, même si supprimée logiquement. Pas de normalisation avancée d'URL, fusion multisource ni mise à jour d'annonce existante en V1. Les champs de préférence, suivi et candidature sont rejetés.

Le lot est transactionnel : toute erreur annule les insertions. Un lot réussi enregistre son résultat ; un rejeu identique le retourne. Même identifiant/contenu différent : 409. Une collision concurrente peut également renvoyer 409 ; relancer ensuite le même lot. L'erreur SQL n'est pas exposée. Corriger un fichier avec un nouveau batch_id, sans réimporter inutilement ses entreprises sans SIREN.

Ne pas envoyer de coordonnées inventées, de contenu confidentiel, ni de HTML à rendre sans échappement. Une information inconnue reste absente ou null. La collecte doit respecter les règles des sources ; aucun scraper n'est fourni.

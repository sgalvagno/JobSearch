# Installation IONOS / Apache

## Prérequis à vérifier sur votre offre

PHP 8.3+ avec PDO MySQL, MariaDB 10.11+, HTTPS, réécriture Apache et possibilité de pointer le domaine vers `public/`. Les capacités exactes dépendent de votre hébergement : ce guide n'est pas une garantie de compatibilité avec toutes les offres IONOS.

1. Créer la base MariaDB et relever hôte, port, nom, utilisateur et mot de passe.
2. Importer `sql/001_initial.sql` dans une base vide depuis l'outil d'administration SQL de l'hébergeur. Le fichier ne crée pas la base ni l'utilisateur et ne doit pas être rejoué sur une installation existante.
3. Déployer le projet, sans `.env`, `.git` ou données de collecte.
4. Copier `config/local.example.php` en `config/local.php`, renseigner la connexion réelle. Laisser `allow_insecure_http=false`.
5. Configurer la racine du domaine/sous-domaine sur le répertoire `public/`. Ne pas déplacer les fichiers de configuration ou SQL dans ce répertoire. Si cette configuration n'est pas possible, revoir le déploiement avant toute exposition.
6. Activer HTTPS et désactiver l'affichage public des erreurs PHP. Vérifier que `.htaccess` est pris en compte et que l'en-tête Authorization arrive dans PHP.
7. Avec SSH/CLI : `php bin/token.php admin '*'` puis `php bin/token.php collector imports:write`. Sans CLI, utiliser la méthode hors ligne ci-dessous ; ne pas créer de route web de génération de jetons.
8. Tester `/api/v1/health` avec le jeton puis une requête sans jeton (401). Vérifier qu'aucun secret/configuration/SQL n'est accessible par HTTP.
9. Configurer limitation de débit et taille des requêtes au niveau hébergement/proxy si disponibles. Sauvegarder la base séparément du dépôt applicatif.

## Jeton sans SSH

Générer localement un secret et son empreinte :

```sh
php -r '$t="js_".bin2hex(random_bytes(32)); echo "SECRET=".$t.PHP_EOL."PREFIX=".substr($t,0,11).PHP_EOL."HASH=".hash("sha256",$t).PHP_EOL;'
```

Dans l'outil SQL, insérer uniquement le préfixe et l'empreinte, jamais le secret :

```sql
INSERT INTO api_tokens(name,token_prefix,token_hash,scopes)
VALUES ('admin','PREFIX_GENERE','HASH_GENERE',JSON_ARRAY('*'));
```

Créer un autre jeton avec `JSON_ARRAY('imports:write')` pour le collecteur. Conserver le secret dans un gestionnaire de secrets ; ne pas utiliser un site tiers pour le générer.

## Maintenance

Révocation : `php bin/revoke-token.php ID`, ou `UPDATE api_tokens SET revoked_at=UTC_TIMESTAMP() WHERE id=ID;`.

Avant une évolution de schéma, faire une sauvegarde et prévoir une migration dédiée. Le SQL initial n'est pas un mécanisme de migration. Tester régulièrement la restauration de sauvegardes. Aucun endpoint de sauvegarde ni accès public aux dumps n'est fourni.

## Proxy HTTPS

L'application ne fait pas confiance à `X-Forwarded-Proto`. Si HTTPS est terminé par un proxy et que PHP ne reçoit pas `HTTPS=on`, configurer le serveur pour transmettre un indicateur fiable. Ne pas contourner ce problème en autorisant HTTP en production.

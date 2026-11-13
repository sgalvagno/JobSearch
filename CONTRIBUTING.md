# Contribuer

1. Ne pas ajouter de dépendance framework sans discussion.
2. Garder le contrat OpenAPI, le SQL, les exemples et la documentation cohérents avec le code.
3. Tester sur une base locale jetable avec Docker ; ne jamais utiliser une base de production pour les tests.
4. Ne commiter ni secrets, ni offres réelles, ni contacts personnels.
5. Décrire les changements de comportement et fournir une migration dédiée pour une base existante. Ne pas modifier uniquement le SQL initial en prétendant migrer les installations.
6. Ajouter des tests HTTP couvrant les permissions, erreurs, idempotence et relations de données.

La CI initiale couvre seulement des smoke tests. La validation OpenAPI, les scénarios de concurrence, les restaurations et un audit sécurité complet restent à ajouter.

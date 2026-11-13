# Sécurité

Cette V1 n'est pas un audit de sécurité. Tester localement puis en préproduction avant exposition.

- HTTPS obligatoire en production ; HTTP autorisé explicitement pour Docker local lié à 127.0.0.1.
- Jetons opaques 256 bits, empreintes SHA-256 en base, secret affiché une fois, révocation et expiration possibles.
- Permissions `data:read`, `data:write`, `imports:write`, `*`. Ne jamais donner `*` à une IA ou au workflow de collecte.
- Pas de jeton administrateur dans un navigateur. CORS n'est pas configuré ; l'API vise les clients serveur et CLI.
- Racine HTTP strictement `public/`. Config, SQL et sources restent hors racine.
- PDO avec requêtes préparées ; noms de colonnes issus de listes autorisées.
- Aucun téléchargement des URL collectées : la V1 ne lance pas de requête vers les sources.
- Corps JSON limité à 2 MiB, aucune donnée de requête/token dans les logs applicatifs. Vérifier aussi les journaux serveur/hébergeur.
- Pas de rate limiting applicatif, quotas ou verrouillage par adresse. Ajouter une protection hébergeur/proxy avant une exposition publique.
- Validation V1 partiellement portée par les contraintes SQL ; les violations produisent une erreur générique 409. Ce n'est pas une validation JSON Schema complète.
- Affichage futur : traiter description et présentation comme du texte non fiable et échapper à la sortie ; aucune garantie de nettoyage HTML n'est fournie.
- Limiter les données personnelles aux informations utiles et sourcées, définir une durée de conservation, permettre leur retrait. La suppression logique ne constitue pas un effacement définitif.
- Sauvegarder et tester les restaurations hors de ce dépôt. Ne jamais publier un dump SQL contenant les données réelles ou des empreintes de jetons.

Ne pas publier de secret ou données personnelles dans une issue. Pour un problème sensible, utiliser un canal privé convenu avec le mainteneur avant de partager les détails ; aucun canal privé dédié n'est configuré dans ce dépôt initial.

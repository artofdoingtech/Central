# Hooks Git versionnés

Ces hooks sont **versionnés** (contrairement à `.git/hooks/`, local et perdu à
chaque clone). Ils s'activent en pointant Git dessus :

```bash
git config core.hooksPath tools/git-hooks
```

**Tu n'as normalement rien à faire** : `scripts/preflight.sh` lance cette commande
à chaque clone (idempotente).

## `pre-push`

Joue `bash tests/run.sh` (les tests unitaires) **avant chaque `git push`** et
**bloque le push** si un test échoue.

- Si la stack Docker de test (`wpcli`) est **arrêtée**, il ne peut pas tester : il
  **prévient et laisse passer** (pour ne pas bloquer un push de docs quand Docker
  est éteint).
- Contourner ponctuellement : `git push --no-verify`.

Prérequis : la stack du clone tourne (`docker compose up -d`).

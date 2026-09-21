# Why this plugin lives in this repository

`moodle-mod_livesession` is a Moodle activity module and has nothing to do with the Xero
PHP SDK that this repository holds. It is staged here only because the GitHub App
authorised for this session cannot create new repositories (`POST /user/repos` returns
403 "Resource not accessible by integration").

**This directory is a staging area, not the plugin's home.** To move it to its own
repository:

```bash
# 1. Create an empty repository on GitHub, e.g. CD1980/moodle-mod_livesession
# 2. From a clone of this repository, on this branch:
cd moodle-mod_livesession
rm WHY_THIS_IS_HERE.md
git init
git add .
git commit -m "Initial import of mod_livesession"
git branch -M main
git remote add origin git@github.com:CD1980/moodle-mod_livesession.git
git push -u origin main
# 3. Delete this directory from the Xero repository.
```

The `.github/workflows/moodle-ci.yml` in this directory only runs once the plugin is in
a repository of its own — GitHub only reads workflow files from the repository root.

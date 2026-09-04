# Translations

`external-image-importer.pot` is the current translation template. Regenerate
it after changing any user-facing string:

```bash
composer run i18n
```

The `.po` files in this folder are the translations inherited from the upstream
Auto Upload Images plugin. Almost every string was rewritten in 4.0.0, so they
no longer match the template and are kept only as a starting point for
translators. The compiled `.mo` files were removed for the same reason.

Only the `.pot` is shipped in the release ZIP; translations for a plugin hosted
on WordPress.org are served from
[translate.wordpress.org](https://translate.wordpress.org/).

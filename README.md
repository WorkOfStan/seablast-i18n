# `Seablast I18n`

A lightweight internationalization (i18n) module designed for apps using the [Seablast for PHP](https://github.com/WorkOfStan/seablast) framework. Installable via Composer, it integrates seamlessly and activates only when needed, allowing you to effortlessly provide multilingual support and manage user language preferences.

## Usage

### UI

Latte filter `translate` which uses dictionary, is set-up in Seablast::SeablastView::renderLatte()
based on the `SeablastConstant::TRANSLATE_CLASS` which is initated in [app.conf.php](conf/app.conf.php).

Use as: `const back = {="Zpět"|translate};`

Note: In latte `SB:LANGUAGE` is only defined, if translation is done before, e.g. Therefore `{=''|translate}` in [views/uls.menu.latte](views/uls.menu.latte) precedes SB:LANGUAGE usage.

```javascript
const back = {="Zpět"|translate};
const lang = {$configuration->getString('SB:LANGUAGE')};
```

To display the language selector, include the three `uls.*.latte` files as follows:

```latte
<!DOCTYPE html>
<html>
<head>
    ...
    {include '../vendor/seablast/i18n/views/uls.css.latte'}
    ...
</head>
<body>
    ...
            <nav>
                <ul id="menu">
                    ...
                    <li>{include '../vendor/seablast/i18n/views/uls.menu.latte'}</li>
                </ul>
            </nav>
    ...
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    {block script}{/block}
    {include '../vendor/seablast/i18n/views/uls.js.latte'}
</body>
</html>
```

Note: The `I18n:SHOW_LANGUAGE_SELECTOR` flag controls whether the contents of all `uls.*.latte` templates are rendered. As a result, you don't need to wrap `uls.*.latte` includes in custom Latte templates with conditional logic—just include them, and the application will decide (via the `I18n:SHOW_LANGUAGE_SELECTOR` flag) whether they take effect.

Instead of language selector, you can switch the language programatically by calling

```javascript
window.languageSelector(string language); // language is a IETF language tag in lowercase, for example: en, fi, ku-latn
```

And you need to take care of the page reload to update localised strings, such as:

```javascript
// switch language using Seablast/I18n mechanics and jQuery
$("select.language_selector").change(function () {
  window.languageSelector($(this).val()).then(
    function () {
      location.reload();
    },
    function (err) {
      console.error(err);
    },
  );
});
```

The `window.languageSelector` functions is declared in the `uls.js.latte`.
That function returns jQuery.Promise (a promise-like object with `.then()`, `.done()`, `.fail()`, `.always()`)
and in the fulfillment value, there's JSON, e.g. `{message: 'en'}`.

Btw: language switching endpoint exists, but the UI selector is gated by `I18n:SHOW_LANGUAGE_SELECTOR` to prevent exposing unfinished/tenant-specific i18n:

```js
const flags = [
  "I18n:SHOW_LANGUAGE_SELECTOR", // turned on by default also in `conf/app.conf.php`
];
```

Note: only languages from the configuration (e.g. `->setArrayString(I18nConstant::LANGUAGE_LIST, ['en', 'cs'])`) are accepted. The first one is the default one.

### Database structure

To create the expected database table structure (for dictionary and localised items), just add the seablast/i18n migration path to your phinx.php configuration, e.g.

```php
    'paths' => [
        'migrations' => [
            '%%PHINX_CONFIG_DIR%%/db/migrations',
            '%%PHINX_CONFIG_DIR%%/../vendor/seablast/i18n/conf/db/migrations',
        ],
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
```

### Dictionary table: `translations`

| Column              | Type        | Attributes                               | Description                                                             |
| ------------------- | ----------- | ---------------------------------------- | ----------------------------------------------------------------------- |
| `id`                | integer     | Primary key, auto-increment (`identity`) | Unique identifier for each translation entry.                           |
| `language`          | string(5)   | Indexed, part of unique constraint       | Language code (e.g., `en`, `en_GB`). `NULL` indicates default language. |
| `translation_key`   | string(255) | Indexed, part of unique constraint       | The lookup key used in the application (e.g., `"Save PDF"`, `"Back"`).  |
| `translation_value` | text        |                                          | Localized string corresponding to the key in the given language.        |

## Integration

- Seablast/Seablast::v0.2.11 contains `APP_DIR . '/vendor/seablast/i18n/conf/app.conf.php', // Seablast/i18n extension configuration` so use at least this Seablast version.
- `"seablast/seablast": "^0.2.7"` is in the `require-dev` section of `composer.json` because the app that uses Seablast I18n may use whatever dev version of Seablast.

### Language API

- API `'/api/language'` using `'model' => '\Seablast\I18n\Models\ApiLanguageModel'` returns the selected language or it receives language to be set in the cookie 'sbLanguage'.
- The cookie 'sbLanguage' is created only after change from the default language.

### Language selector

- Because typically `.htaccess` uses `RedirectMatch 404 vendor\/(?!seablast\/)` to make vendor folder off limits for web access except the seablast library, the jquery.uls is in [Seablast for PHP](https://github.com/WorkOfStan/seablast) since v0.2.11 and not in this module.
- However, it's useful to know that to make the SVG icon in `.uls-trigger` adopt the `font-color` of the surrounding element, the following style was added into `uls/images/language.svg`: `fill="currentColor"`. Also `uls/css/jquery.uls.css` was changed (changed: `.uls-trigger`, added: `.uls-trigger icon` and `.uls-trigger .icon svg`).
- Language is lazy inititated in SeablastView `$translator = new $translatorClass($this->model->getConfiguration());` which instantiates SeablastTranslate from which `$lang = new ApiLanguageModel($this->configuration, new \Seablast\Seablast\Superglobals());` is called. There `$this->configuration->setString('SB:LANGUAGE', $result);` is set.
- `'/api/language'` using `'model' => '\Seablast\I18n\Models\ApiLanguageModel'` is called from window.languageSelector when uls.onSelect with parameter.

### Localised data access

Extend the class FetchLocalisedItemsModel with preset of these three properties

```php
    /** @var int itemTypeId set in the child class */
    protected $itemTypeId;
    /** @var string page title beginning set in the child class */
    protected $titlePrefix = "";
    /** @var string page title ending  set in the child class*/
    protected $titleSuffix = "";
```

in order to access the localised items filtered by their type.

The full class looks like this:

```php
<?php

declare(strict_types=1);

namespace WorkOfStan\Protokronika\Models;

use Seablast\I18n\Models\FetchLocalisedItemsModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;

/**
 * Retrieve items from database
 */
class BlogModel extends FetchLocalisedItemsModel
{
    use \Nette\SmartObject;

    /**
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->itemTypeId = 1; // Blog type ID
        $this->titlePrefix = "My special web - ";
        $this->titleSuffix = "Blog";
        $configuration->mysqli(); // dbms prefix set up even if Seablast\Auth is not present and thus it's not
        //already set up in SeablastController: `$this->identity = new $identityManager($this->configuration->mysqli());
        parent::__construct($configuration, $superglobals);
    }
}
```

Todo: find a way to initiate mysqli() automatically, so that it's not dependant on the Seablast\Auth presence.

(See [Seablast\Dist BlogModel.php](https://github.com/WorkOfStan/seablast-dist/blob/main/src/Models/BlogModel.php).)

This MODEL yields items one by one from the database in a lazy, memory efficient, way.
The VIEW can display it as follows:

```latte
{layout 'BlueprintWeb.latte'}
{block mainblock}
<h1>
    {ifset $itemId}
        <a href="{$configuration->getString('SB_APP_ROOT_ABSOLUTE_URL')}/blog-r">Blog (read-only)</a>
    {else}
        Blog
    {/ifset}
</h1>

{foreach $items as $item}
    <article class="post">
        {* Check if $itemId is set to decide if we have multiple posts - If we have an “id” parameter, we’re in single‐post mode *}
        {ifset $itemId}
            {* single‐item mode: editable fields for admins *}
            <h2>{$item['title']}</h2>
            {*<p class="post-date">Created at: {$item['created_at']}</p>*}
            <div class="post-content">{$item['content']|breakLines}</div>
        {else}
            {* listing mode: links into each item TODO paging *}
            <h2><a href="?id={$item['item_id']}">{$item['title']}</a></h2>
            {*<p class="post-date">Created at: {$item['created_at']}</p>*}
            <div class="post-content">{$item['content']|breakLines}...</div>
        {/ifset}
    </article>
{/foreach}
{/block}
```

This code can be seen live in [Seablast\Dist blog-readonly.latte](https://github.com/WorkOfStan/seablast-dist/blob/main/views/blog-readonly.latte).
The texts can also be directly editable by admins as seen in [Seablast\Dist blog-editable.latte](https://github.com/WorkOfStan/seablast-dist/blob/main/views/blog-editable.latte).
(This of course requires users to be logged in, hence [Seablast\Auth](https://github.com/WorkOfStan/seablast-auth) is required.)

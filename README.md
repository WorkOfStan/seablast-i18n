# seablast-i18n

Seablast-i18n is a lightweight internationalization (i18n) module designed for apps using the Seablast for PHP framework. Installable via Composer, it integrates seamlessly and activates only when needed, allowing you to effortlessly provide multilingual support and manage user language preferences.

## Integration

- Seablast/Seablast::v0.2.11 contains `APP_DIR . '/vendor/seablast/i18n/conf/app.conf.php', // Seablast/i18n extension configuration`

### Language selector

- To make the SVG icon in `.uls-trigger` adopt the `font-color` of the surrounding element, the following style was added into `uls/images/language.svg`: `fill="currentColor"`. Also `uls/css/jquery.uls.css` was changed (changed: `.uls-trigger`, added: `.uls-trigger icon` and `.uls-trigger .icon svg`).
- Language is lazy inititated in SeablastView `$translator = new $translatorClass($this->model->getConfiguration());` which instantiates SeablastTranslate from which `$lang = new ApiLanguageModel($this->configuration, new \Seablast\Seablast\Superglobals());` is called. There `$this->configuration->setString('SB:LANGUAGE', $result);` is set.
- `'/api/language'` using `'model' => '\WorkOfStan\Protokronika\Models\ApiLanguageModel'` is also called from mit.js::window.languageSelector. First without parameter to get the language info (is it necessary, when in SB:LANGUAGE ? Maybe lazy.) Then when uls.onSelect with parameter.

## TODO

- 250720, migrations
- 250720, SB:LANGUAGE .. zatím je jen v pt a vlastně indikuje místa k přenesení sem
- 250720, nechť changelog ULS (vč. verze) je popsáno zde (ULS adresář expected v Seablast:v0.2.11)
- 250720, cookies nechť jsou ve správném adresáři
- zmínit filter Latte --- to je asi už v conf, že?
- '%%PHINX_CONFIG_DIR%%/../vendor/seablast/i18n/conf/db/migrations', ... expected --- just like in Auth

.PHONY: install
install: vendor node_modules static/main.css

vendor:
	composer install
	rm -rf vendor/twig/twig/doc
	rm -rf vendor/twig/twig/src
	rm -rf vendor/twig/twig/test
	rm -rf vendor/symfony/yaml/Tests

node_modules:
	npm install

%.css: %.scss node_modules
	sassc $< > $@

.PHONY: pupupu.zip
pupupu.zip:
	cd .. && zip -r -FS pupupu/$@ pupupu/*.php pupupu/README.md pupupu/.htaccess pupupu/static/ pupupu/templates/ pupupu/themes/ pupupu/vendor/ pupupu/node_modules/font-awesome/css/ pupupu/node_modules/font-awesome/fonts/ pupupu/node_modules/simplemde/dist/

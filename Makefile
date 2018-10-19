.PHONY: install
install: vendor node_modules static/main.css

vendor:
	composer install

node_modules:
	npm install

%.css: %.scss node_modules
	sassc $< > $@

pupupu.zip:
	cd .. && zip -r -FS pupupu/$@ pupupu/index.php pupupu/static/ pupupu/templates/ pupupu/themes/ pupupu/vendor/ pupupu/node_modules/font-awesome/css/ pupupu/node_modules/font-awesome/fonts/ pupupu/node_modules/simplemde/dist/

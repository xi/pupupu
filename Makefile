.PHONY: install
install: vendor node_modules static/main.css

vendor:
	composer install

node_modules:
	npm install

%.css: %.scss node_modules
	sassc $< > $@

# pupupu - simple static CMS for crappy servers

[Static website generators](https://www.staticgen.com/) are awesome because of
two reasons:

-   the website is fast and secure because no code is running on the server
-   developers can use all their favourite tools (e.g. text editors or git)

The big downside is that usability for non-developers is poor because they
usually do not know about text editors and git.

[Lektor](https://www.getlektor.com/) calls itself a "static content management
system". It is a static website generator, but it also has a web UI to edit the
source files. This way, it feels a lot like a CMS while maintaining most of the
benefits of a static website generator.

Unfortunately, many hosters still only offer PHP. So Lektor, which is written
in python, is not an option.

So here I present: A simple static CMS for crappy servers!

## Quickstart

-   Get the latest zip file from <https://github.com/xi/pupupu/releases>
-   Unpack to your webserver
-   Create your templates in a folder called `_templates/`. For starters, you
    can copy an example theme from `pupupu/themes/`. Don't forget to add some
    CSS!
-   Open `https://yourdomain.com/pupupu/` in a browser
-   Start creating your content!

## Documentation

### Folder Structure

The following files and folders are relevant for your project:

-   `/pupupu/` - UI for editing
-   `/_templates/` - template files
-   `/_templates/default.html` - default template
-   `/_content/` - source files
-   `/_content/_site.yml` - contains site-wide config
-   `/_content/_users.yml` - password hashes
-   `/files/` - uploaded files

### Content

For each page, there is a corresponding yaml and markdown file in `/_content/`.
The template can be defined in the yaml file using the `_template` key. If none
is specified, `default.html` is used.

Each time a page is saved in the UI, it automatically regenerated. If the site
config is saved, all pages are regenerated. If a page uses the API to include
content from other pages, you can add it to `_dynamic` in the site config to
regenerate it each time any page is saved.

#### Files and Folders

Most pages will be represented as a folder containing the files `index.yml` and
`index.md`. This will generated a folder containing `index.html`. Browsers can
skip the `index.html` part, resulting in nice URLs.

Still, there are some cases where you need to control the file name. For this
reason, a different pattern is used if the page name contains a dot: If you
create a page called "feed.xml", the corresponding files are called
`feed.xml.yml` and `feed.xml.md` and will generate `feed.xml`. (Pages that have
a dot in their name can consequently not have subpages.)

### Templates

[Twig](https://twig.symfony.com/) is used as templating system. The following
variables are available in a template:

-   `path` - path to current page
-   `page` - the data from the page's yaml file
-   `body` - the contents of the page's markdown file
-   `site` - the data from `/_content/_site.yml
-   `pupupu` - an interface through which you can access arbitrary data (useful
    for feeds or index pages). Please refer to the source code for a list of
    methods.

There are also some special filters available:

-   `md` - render markdown using [PHP Markdown
    Extra](https://michelf.ca/projects/php-markdown/extra/)
-   `shift_headings` - useful to fit user-generated content into the document
    outline

# SQLantern - The Multi-Panel Database Manager
Current version: v1.9.7&beta; (public beta) | [Changelog](CHANGELOG.md)\
License: [GNU General Public License v3.0](LICENSE)\
[Українською](README_uk.md)

SQLantern is a database manager (more like a data viewer) in a web-browser, designed for high productivity, with a fresh concept: displaying multiple database tables side by side in separate panels.\
It is a tool for all kinds of database users, professionals and amateurs alike: programmers, QA specialists, database architects, analysts, database administrators.\
It turned out to be very useful for educational purposes, demonstrations, teaching, and learning.

It is a parallel, multi-threaded (each panel works independently), multi-server, multi-login, multi-database, multi-table data management tool.\
You can view the data horizontally (panels) and/or vertically (screens).\
Build a high tower of panels, or a long wall, or combine both to build the Great Wall of Panels.\
Organize your work how you like.

![](https://sqlantern.com/images/sqlantern_great_wall_of_panels.jpg)

SQLantern is an in-browser web-application (a single-page application), written in HTML, CSS, JavaScript, and PHP.\
It requires a web browser and a web server to work.\
It currently supports MariaDB/MySQL and PostgreSQL.\
It is open source and free software.

This tool is aimed at people who work with databases in the same manner I do:
- ALWAYS with multiple database tables,
- MOSTLY with multiple databases,
- VERY OFTEN with multiple database servers.

SQLantern unifies that kind of work in one browser tab/window.\
If that's how you work and what you need, check it out.

Visiting the official demo is highly recommended: https://sqlantern.com/<br>
(It's better to see and try something than read about it, right?)

## Multi-Panel Database Manager
There are 4 kinds of panels in SQLantern, and any amount of panels of any kind can be open at the same time, and reordered as you like.\
There are no limitations on the number of open panels, only your device's resources are the limit.

### Connections panels
![](https://sqlantern.com/images/en_connections_panel.jpg)\
Whatever you intend to do and whichever set of panels you end up with, this is always the start.\
The Connections panel can display a form to add a new connection, a list of currently available connections, or both.\
When you add a connection or click one of the connections in the list, the next kind of panel opens up...

### Databases panels
![](https://sqlantern.com/images/en_databases_panel.jpg)\
This is the list of databases at the specified server the chosen login has access to.\
As usual, you can have multiple different database lists on the screen (different logins and/or servers), or multiple instances of the same list.

### Tables panels
![](https://sqlantern.com/images/en_tables_panel.jpg)\
This is the list of tables in the chosen database.\
Click one of the tables to open the most important type of panel...

### Table data panels
Those panels are, in fact, arbitrary SQL requests panels, they just happen to start with a chosen table data for users' convenience.\
You can and are supposed to run any queries you need in those panels.

You can have multiple panels with data from _different tables_ side by side:\
![](https://sqlantern.com/images/en_table_data_panel__many_tables.jpg)

Or multiple panels with the data from _the same table_:\
![](https://sqlantern.com/images/en_table_data_panel__same_table.jpg)

All in all, SQLantern displays data side by side from the same table or from different tables, from the same database or from different databases, from the same server or from different servers, all in the same browser tab.

## Screens in SQLantern
Putting too much side by side is not always logical and convenient, even when working within one database.\
To add one more dimension to panels, multiple "screens" are available.\
A screen is an independent set of panels.\
(You can only see and work on one screen at a time.)\
Click the "Add Screen" icon to add a screen: ![](https://sqlantern.com/images/icon_add_screen.png)

Click "Previous screen" and "Next screen" to go to the adjacent screens: ![](https://sqlantern.com/images/icon_next_screen_prev_screen.png)\
Click "Switch screen" to switch to an arbitrary screen or move panels between screens: ![](https://sqlantern.com/images/icon_switch_screen.png)

![](https://sqlantern.com/images/sqlantern_screens.jpg)

## SQLantern is not...
SQLantern is not "better" or "worse" than alternatives (phpMyAdmin, adminer, etc), it is very different from them.

It doesn't have many of the functions the alternatives have, and the alternatives don't provide what SQLantern offers, which in my mind makes any comparisons unreasonable.

In my opinion, popular database managers (phpMyAdmin, adminer, pgAdmin, and other) are very good in _database management_ and _database design_, but barely acceptable for viewing the data and working with the data. So, that's the gap SQLantern is intended to fill: it has no database management and design features, but focuses on _displaying_ the data conveniently and efficiently.\
(And naturally you can run any SQL request in SQLantern and manage the databases with manual commands. There are just no visual features, which would simplify it.)

## Logins, Hosts, Ports, Drivers
Use login `example` to connect as user `example` to the _default host_ using the _default port_.\
Default host is `localhost`, and default port is `3306` (both are configurable).\
Login `example` is the same as `example@localhost`, and the same as `example@localhost:3306`.

To connect to a _non-default host_, add "@" followed by the host name or IP.\
E.g. `example@example.com`, `example@1.1.1.1`.\
Multi-host must be enabled to connect to other hosts (see server-side options further below).

Default host can be any host or IP, local or remote.\
If you need to connect to only one host, but it's not `localhost`, don't enable multi-host connections, only change your default host (see server-side options further below).

To connect to a _non-default port_, add the host (even if it's a default host), followed by ":", followed by the port number.\
E.g. `example@localhost:33306`, `example:1.1.1.1:30306`.\
You have to always specify the host when using a non-default port, even when connecting to the default host.\
You have to change the ports-to-drivers schema to use non-default ports at all (see server-side options further below).\
You can have a default port other than 3306, that is also changed in server-side options.

_Single-file version can only connect to `localhost` on ports `3306` and `5432`._
(Unless you know how and modify the source code of your copy.)

## Query Profiling
**Profiling** is a very potent and extremely important feature of SQLantern, hidden behind a small icon, which makes it easy to miss if you don't know it's there.\
(Although it is one of the core features and reasons SQLantern was created in the first place.)\
The stopwatch icon in any tables' panel will toggle that panel between the tables' list and query profiling.\
![](https://sqlantern.com/images/en_profiler_overview.jpg)

Profiling has it's own dedicated [README file](README_profiler.md).

## Features
SQLantern works both inside a website directory (like `https://example.com/sqlantern`) or on a subdomain (like `https://sqlantern.example.com/`).

Any query can be run recurrently, endlessly, with an arbitrary pause between runs.\
Although rarely needed, it's a nice feature to have when monitoring some progress or even for the obvious `SHOW FULL PROCESSLIST`.\
Press an arrow next to "Run" to open the timer setting. When this setting is open (visible), the query will run periodically.\
Multiple queries can be run recurrently in parallel, in different panels.

Panels can be named to add context (use the pen icon: ![](https://sqlantern.com/images/icon_custom_name.png)).

`Tab` pressed inside the `Query` inserts a `Tab character` in place.\
`Tab` when multiple lines are selected adds a `Tab character` at the start of each line (like you'd expect in a text editor).\
`Shift + Tab` with multiple lines selected removes a `Tab character` from the start of each line (like you'd expect in a text editor, again).\
`Enter` inserts a new line.\
`Ctrl + Enter` runs the query.

To go to an arbitrary page of a multi-page data list, you can click the page number input, change it to a desired value, and press `Enter`.

SQLantern supports MariaDB/MySQL and PostgreSQL out of the box.\
If you need other database systems, please help me write the server-side code for it (it's not a big deal, I promise).

Only single query execution in each `Query` field is implemented and it will stay this way.\
(A possible workaround is abusing "Import" with text input, but you won't get results for SELECTs, only the overall quantity of executed queries.)

There is a built-in `Notepad` for text notes, which is saved to an internal browser storage per-domain ("LocalStorage").\
I.e. this text is only there as long you are in the same browser on the same domain. Different browser = different Notepad. Different domain = different Notepad. (Same browser in incognito mode = different Notepad, as well.)

There are no database design features and there won't be any, to keep the code lightweight. Please, do not treat it as an issue, database design features are not planned to be added.\
The project is intended to be used as _one of the tools_ (alongside `phpMyAdmin`, `Adminer`, `PgAdmin`, or any other software of preference), not the _one and only_ tool.

## System Requirements
A server with PHP 5.6+\
Sessions enabled in PHP\
A web browser with enabled cookies

DOES NOT need internet connection (contains no external links/resources)

Tested with:
- MariaDB 10.1.37+ (released in 2018)
- MySQL 5.5.52+ (released in 2016)
- PostgreSQL 11.12 (released in 2021)

## Installation
There is no installation procedure, SQLantern works right away after deploying the source files.\
There is a full version, a single-file version, and CMS-integrated versions (for Joomla and OpenCart).\
The version to choose depends on your needs.

Top reasons to choose the _full version_ are:
- You need to use non-standard ports,
- "localhost" is not the default host you need,
- You need multi-host connectivity.

If you don't know or aren't sure which version you need, you'll probably be fine with a single-file version or a CMS version.

If you're experienced with git and want easy updates or need a customly configured fork, you should clone this repository.

If you can't or don't want to use git, but need other configuration than the single-file version has, you should use the full version without git.

### Install CMS-integrated version
This is my top recommendation, because these versions are the most secure, easy to install and straightforward, giving a two-click immediate access to the website database.\
If you only need to work with that single database, check this version out.\
The downside is, of course, that you can **only** access a single database. Adding other connections is not possible.

_Currently only available for Joomla (v3.x, v4.x) and OpenCart/ocStore (v2.3, v3.x)._\
Read more in [the dedicated GitHub repository](https://github.com/nekto-kotik/sqlantern-cms-modules).

Download the zip files from [sqlantern.com](https://sqlantern.com/) or [the dedicated GitHub repository](https://github.com/nekto-kotik/sqlantern-cms-modules) and install it just as any extension inside your CMS.

### Install single-file version
This is my second recommendation for most users.\
If you can't or don't want to use git, or unzip files on the server (and maybe you only have FTP access and that's it), or upload multiple files, this is the version to try.\
I believe it's the "one size fits all" version for the widest audience. It's the fastest one to deploy (the most portable) and works in most cases. (And it's also more friendly for the less experienced folks.)\
Even I myself always use it with the new projects, it's just faster.

The most important and critical difference from the full version is that single-file SQLantern doesn't read configuration files at all ("config.sys.js" and "config.sys.php"), with the following consequences:
- single-file version only connects to "localhost",
- single-file version only connects to standard ports (3306 for MariaDB/MySQL and 5432 for PostgreSQL).

Download the file "sqlantern.php" from [sqlantern.com](https://sqlantern.com/) or "Releases" on this GitHub repository, and copy it to a desired location.

### Run SQLantern in Docker
If you don't use PHP in your stack (or just prefer Docker over other options), there is a small footprint SQLantern Docker container (~30MB).\
Read details in the [Docker hub repository](https://hub.docker.com/r/nektowastaken/sqlantern), or [download and build the Dockerfile from GitHub](https://github.com/nekto-kotik/sqlantern-docker/).

### Install from GitHub
Clone this repository to a location of your choice:\
`git clone https://github.com/nekto-kotik/sqlantern.git` (will create "sqlantern" subdirectory)\
or\
`git clone https://github.com/nekto-kotik/sqlantern.git .` (will clone into current directory)

The biggest upside of this version, in my opinion, is easy further updates with `git pull`.

### Install full version without git
Download the full version zip from [sqlantern.com](https://sqlantern.com/) or "Releases" on this GitHub repository, and unzip it in a desired location.

## Workspace Sessions and continuing work where you left off
If you browser _discards_ the tab, SQLantern automatically restores itself when you return to it.\
However, it won't (can't, really) automatically self-restore after the browser is closed or the page is manually refreshed.\
So, if you need to continue your work from where you left it, _save your session_ and _restore_ it when you return (under the Sessions icon, ![](https://sqlantern.com/images/icon_sessions.png)).\
The other option is not closing the browser (put your device into sleep or hybernation).

## SQLantern uses `LocalStorage`
SQLantern uses "LocalStorage", which requires enabling cookies (at least for the domains where you're using SQLantern).\
(That's right, enabling cookies also enables LocalStorage, don't ask...)\
LocalStorage is used to store settings, working sessions, saved queries and the built-in Notepad.\
If LocalStorage is disabled, SQLantern doesn't run at all, and no error is given.

### LocalStorage is not very safe
Be advised that working with confidential data on untrustworthy devices is always risky.\
SQLantern should not be used on devices shared with random or suspicious users, or strangers.

### LocalStorage can be accidentally erased
A user can accidentally unknowingly erase their whole LocalStorage completely, when clearing browsing data.\
Different browsers do it a bit differently: some clear LocalStorage when deleteing cookies, other have a dedicated "clear data" checkbox, etc.\
What's important, is that you can accidentally lose _all SQLantern data on all domains_ (settings, sessions, saved queries, notepad).\
The only solution as of now is: don't do it, don't delete the browsing data.

## Translations
Two languages are shipped with SQLantern: Ukrainian and English.\
Any translation volunteers out there? :-)

## Export and Import Issues
Export/Import are currently only available with MariaDB/MySQL. PostgreSQL support will be added in version 2.

There are no **export** issues I'm aware of.\
Exported data is a standard SQL dump and can be imported in any other program.

On the other hand, **import** is built on compromises and sometimes fails without any good cause.\
It works well without any known issues on average databases (smaller than 300M and below 2,000,000 records in one table).\
But it often doesn't work with bigger datasets with no apparent reasons.\
To keep the code short and simple, PHP database drivers' functions are <del>abused</del> used in such a way, which makes the import completely dependent on the RAM available to PHP and database's "maximum allowed packet size".\
But even working fully within the limitations, when importing bigger-than-average databases, sudden failures occur.\
I don't know the root cause of it yet, but I highly suspect I won't be able to solve it while keeping the small design.

**In short, import either works well with a given database, or it doesn't work with it at all.**

My recommendations about import are:
- Test it with the databases you're going to use it with
- Use it if it works with it
- Don't use it if it doesn't work (import won't magically start working: if it fails with some dump, it will always fail with it)
- Have other tools for import at hand in any case

I don't consider this a high-priority issue, because SQLantern wasn't initially supposed to have Export and Import at all.\
These functions are an afterthought and bonus content, so enjoy them if they work for you, and I'm sorry if they don't, but it probably won't be fixed.

## Other Known Issues
Export and Import of MERGE tables are not supported.

Please, read the `Won't Fix` list further below, too, for more `Known Issues`, which I don't consider issues.

## Custom configuration
Many things are configurable visually, but they are only stored in the browser per-domain, and if you need multiple copies with the same settings, there is another way to configure SQLantern for mass-deployment.\
Also, there are configurable options, absent from the visual settings.

When `php/config.sys.php` or `js/config.sys.js` are mentioned below, you must create those files or edit existing files (under the SQLantern root directory).\
This way, when you update your copy of SQLantern, your custom configuration is left intact (`php/config.sys.php` and `js/config.sys.js` are not part of SQLantern, they are designed to be custom-only and per-client).\
This also allows you to have your own fork (with your own configuration) to clone/deliver on your servers instead of the official SQLantern repository (and get the same custom configuration on multiple locations, with zero merge issues).

**Beware: Single-file version doesn't read configuration files and can't be configured beyond what's visually configurable!**

### Different default language
Add "config.language = 'uk';" into `js/config.sys.js`.\
Use "en" for English, "uk" for Ukrainian. Those are the only two languages available currently out of the box.

### Other front side options
`Auto-resize` is enabled by default, but this can be changed by adding "config.default_auto_resize = false;" into `js/config.sys.js`.

The `Query` textarea is visible by default, but you can hide it by clicking the `Query` text.\
It can be hidden by default (in all new panels), if you don't usually write your own queries.\
Add "config.default_open_query = false;" into `js/config.sys.js` to make it hidden by default.

Automatic colour coding in SQLantern is enabled by default: each new open database gets a random colour, and then the tables open from this database inherit that colour.\
Or, in other words, all tables of the same database are colour coded automatically.\
This auto-colouring can be disabled by adding "config.auto_color = false;" into `js/config.sys.js`. All new panels will be the same default colour in this case.

You can add your own CSS on top of the existing default style without changing the original CSS (and update without merge later)!\
Add "config.styles = '{filename}'" into `js/config.sys.js` to do that.\
All paths must be inside the "css" directory, and file names must be written without the ".css" extension.\
(In other words, remove "css/" from the beginning and ".css" from the end.)\
E.g. "config.styles = 'sqlantern/td-400';" to enable a CSS file, which is actually "css/sqlantern/td-400.css".\
You can add multiple CSS files! Separate them with a space in this case.\
E.g. "config.styles = 'sqlantern/td-400 my/custom';" to add two files: "css/sqlantern/td-400.css" and "css/my/custom.css".

### Back side options
Only the most important options are listed below. See `php/index.php` for all the options with explanations.\
Add `define("{optionName}", {desiredValue});` into `php/config.sys.php` to change those default values.\
E.g. `define("SQL_DEFAULT_HOST", "remotehost");` to change default host.

`SQL_ROWS_PER_PAGE` (int) sets the number of rows per page (30 by default).\
_NOTE: This option will most likely be moved to the front side configuration in Version 2._

`SQL_DEFAULT_HOST` (string) sets default host ("localhost" out of the box).\
Default host is applied when a login without a host is used ("notroot" being a login without host, "notroot@192.168.0.0" being a login with host).

`SQL_DEFAULT_PORT` (int) sets default port (3306 out of the box).\
Default port is applied when a login without a port is used ("notroot" being a login without port, "notroot@localhost:33006" being a login with port).

`SQL_DISPLAY_DATABASE_SIZES` (boolean) enables and disables displaying database sizes in the database list.\
While it is usually very useful, far too often it's unbearably slow, thus it is disabled by default (default is boolean `false`, use boolean `true` to enable).

`SQL_SHORTENED_LENGTH` (int) sets the length, to which values are shortened, when "Full texts" is not checked.\
Default value is 200.

`SQL_MULTIHOST` (boolean) enables and disables multi-host operations. Disabled by default (boolean `false`).\
When `false` (default), the SQLantern instance will only exclusively connect to the `SQL_DEFAULT_HOST` host and won't allow other hosts.\
**Beware that enabling it basically allows using your copy of SQLantern to brute force or DDOS attack other servers.**\
Please, protect your SQLantern instances with additional measures when you enable multi-host operation (e.g. filter access by IP).\
_Security is your responsibility when you enable this option_.

## Tips and Recipes

### Connection expired while you were working
Add a panel and login again. You can continue using the already open panels after that.

### How to PostgreSQL
In SQLantern, the connection port defines the database driver to use.\
With that in mind, if you use the standard 5432 port, you can specify the port in the login, e.g. "postgres@localhost:5432", "postgres@example.com:5432", etc.

If you use the standard 5432 port, and want to _always_ connect to PostgreSQL without specifying the port in the login (connect to PostgreSQL by default), add `define("SQL_DEFAULT_PORT", 5432);` into your `php/config.sys.php`.

If you use non-standard ports, you must _add_ those ports into ports-to-drivers relations in your `php/config.sys.php` (the `SQL_PORTS_TO_DRIVERS` constant), but you must _keep the original values_, too.\
For example:
```
"SQL_PORTS_TO_DRIVERS" => json_encode([	// `json_encode` for PHP 5.6 compatibility!
  3306 => "php-mysqli.php",
  5432 => "php-pgsql.php",
  55432 => "php-pgsql.php",  // non-standard port for PostgreSQL
  55532 => "php-pgsql.php",  // another non-standard port for PostgreSQL
]),
```

If you use non-standard port, AND want to connect to PostgreSQL without specifying the port in the login (connect to PostgreSQL by default, using non-standard port), add both `SQL_PORTS_TO_DRIVERS` AND `SQL_DEFAULT_PORT` into your `php/config.sys.php`.

If "postgres" database doesn't work for connection, add `define("SQL_POSTGRES_CONNECTION_DATABASE", "nonpostgres");` into your `php/config.sys.php` (you must know what works in your case, I can't give you any advice, it's individual).

_No custom configuration described above is possible in single-file SQLantern_, it doesn't read configuration files and can only use default ports (3306 for MariaDB/MySQL, 5432 for PostgreSQL).\
_(Or you can change the source code of your single-file SQLantern copy, if you know how to do it.)_

## Won't Fix
Running multiple queries from one Query input isn't supported **and isn't planned**.\
Trying to run multiple queries in one `Query` box (separated by ";", which is legal directly in console or in e.g. phpMyAdmin) results in an error.

There are no data editing features. And I don't think we'll implement any, ever.\
Again, SQLantern is not intended to replace or imitate phpMyAdmin or other powerful data managers, it is made to _complement_ them.

There are no limits/breaks/pages in database lists and table lists.\
E.g. if the login you use has access to 1000+ databases or tables, _all_ of them will be listed in one big list, without any pagination.

There is no grouping or tree-like display of databases or tables, and there won't be any.

When querying large tables with a bit `LIMIT`, server or browser might run out of memory (or the server might run into a timeout).\
SQLantern doesn't force break the results into pages for queries with `LIMIT`, because I assume that if your query has `LIMIT`/`LIMIT ... OFFSET`, _you need it_ and that's _your resposibility_.\
(But results for queries without `LIMIT` are broken into pages.)

There is no auto-complete and it is not planned, as I hate it and disable it everywhere for myself (where possible).

## Be Aware of The License
This project is published under a "viral" license, which many find pretty hardcore, be wary of it.\
If you're only using SQLantern for yourself for ANY purpose without changing the source code and/or redistributing it, this does not affect you at all.

## Roadmap

A reasonable complete guide will be published somewhere before version 2.

**Version 2** should be released in Q2 2024 and will have the following improvements:
- <del>Support Views</del> (implemented in version 1.9.2)
- A way to back-up the whole LocalStorage (configuration, sessions, saved queries, notepad) on to the server or to the client\
  (against accidental erase by the user)
- PostgreSQL export and import
- MS SQL driver (PHP)
- Download "binary" data
- Rows per page will be a customizable drop-down select (per-panel)
- More setting will be visually configurable (rows on page, number format, default port, and more)

**Version 3** should be released somewhere in Q4 2024, with the following improvements:
- Sharing sessions
  - Ability to share everything you have open with just a single link.
  - It sounds dangerous and there are risks involved, but the idea is well-thought-through and will be as secure as possible with some additional security options (like self-remove after the first use).

**Beyond Version 3**...\
There are no more detailed further plans.\
But the further (very low priority) desired features look like that (in random order):
- Fully working mobile version
- Restore closed panels/screens (panels history)
- Manual panel resizing (width)
- RTL support
- More database drivers
- Horizontal split for 4K and higher resolution displays (fit two screens on one, maybe four screens)
- Prolong/renew PHP sessions automatically
  - I'm on the fence, there are importants cons to doing that
- Back-end in other program languages
- Fully local portable desktop version (still working in browser, but not needing a server for the back side)

## Copyright
SQLantern PHP code:\
(C) 2022, 2023, 2024 Misha Grafski aka nekto

SQLantern JS, HTML, CSS code:\
(C) 2022, 2023, 2024 Svitlana Militovska

Simplebar:\
Made by Adrien Denat from a fork by Jonathan Nicol\
https://github.com/Grsmto/simplebar

## Donations
If you feel an absolutely unstoppable desire to make a donation to the SQLantern authors, [you can buy us a coffee](https://www.buymeacoffee.com/nekto).\
GitHub Sponsors are not available in Ukraine yet (where we reside).

## Code Licensing
If you are making a proprietary or closed source app and would like to integrate SQLantern into it, contact sqlantern@sqlantern.com for non-GPLv3 licensing options.
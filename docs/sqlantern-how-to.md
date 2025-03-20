# SQLantern How-Tos (Tips and Tricks)
License: [GNU General Public License v3.0](LICENSE)\
[Українською](sqlantern-how-to_uk.md)

## `CONNECTION FAILED` while working with the database
You'll be occasionally getting `Error [...] CONNECTION FAILED` instead of a response from the server while working in SQLantern.\
This is normal and expected. Here's how I recommend to deal with it.

![](https://sqlantern.com/images/en_connection_failed.png)

`CONNECTION FAILED` means that the PHP session has expired _on the server_, thus the server has lost your connection credentials and cannot connect to your database or databases anymore. You need to resend your login and password (each of them if you use multiple connections simultaneously).\
(Technically speaking, to be precise, the server doesn't even have your passwords - they are encrypted and stored in the browser, while the server only stores the encryption keys, which are randomly generated for every new connection.)

### Do the following to reconnect and continue working
- DO NOT close anything of refresh the page. (You _can_ if you _want_, but you _don't need to_.)
- Add a new connections' panel - press the ![](https://sqlantern.com/images/icon_add_panel.png) icon. There will be no connections there, it'll ask you for a login and password.
- Enter login and password. Connect.
- Close this newly created panel (which transformed into databases' panel).
- If you need multiple connections, repeat this process.
- Continue working. You can and should continue using the already open panels like nothing happened.

### There are two possible ways to see `CONNECTION FAILED` less often
- Increase `session.gc_maxlifetime` PHP setting. This might make the server less secure, so I _don't recommend_ it and won't go into details how to do it. Only experiences professionals should make this change.
- <del>Use the `Keep-alive` feature - press ![](https://sqlantern.com/images/icon_keep_alive.png) in any tables' panel. It _usually_ prolongs the server-side session (PHP sessions' expiration is complicated), although cannot prevent it completely (especially if the browser tab with SQLantern gets _discarded_ from memory).</del> (Keep-alive is now always enabled automatically.)

## Save your current workspace (everything you have open) and return to it later
SQLantern lets you save and restore "sessions", which means it can save (and restore) all the panels and all the screens in the browser tab you're working in.\
Just click the "Sessions" icon - ![](https://sqlantern.com/images/icon_sessions.png) and choose a desired action (save or restore):\
![](https://sqlantern.com/images/en_sessions.png)

Sessions are saved in the browser you're working in, in the browser's `LocalStorage`.\
If "LocalStorage" is accidentally (or purposefully) deleted, all your sessions will be lost (along with all your Saved Queries, Notepad and settings).\
Here are the ways to back up "LocalStorage":

### Back-up your SQLantern data to your computer (download your SQLantern LocalStorage)
![](https://sqlantern.com/images/en_back_up_to_client.png)

Go to Sessions / Backup and click "Save (download) backup".\
This will propose you to save a `json` file onto your computer.\
This file will contain: Sessions, Saved Queries and Notepad.\
**Be aware**: The downloaded back-up files contain _plain-text non-encrypted JSON data_. Be careful with those files and don't leak them accidentally.

To restore the back-up, use the reverse action - `Restore (upload) backup`.

### Back-up your SQLantern data to the server
This feature requires setting `SQLANTERN_SERVER_SIDE_BACKUPS_ENABLED` to `true`.\
Ideally you should also set a custom non-obvious value to `SQLANTERN_SERVER_SIDE_BACKUPS_FILE` (but you must clearly understand what to set it to).

![](https://sqlantern.com/images/en_back_up_to_server.png)

Go to Sessions / Backup, choose "At this server" there, enter a _complicated high-entropy password_ of your choice and click "Save to server".\
Server-side back-ups are stored into a separate PHP file, the data is encrypted and is reasonably secure (bcrypt with work factor 13 and AES-256-CBC with randomly generated salt and IV are used).\
A password is required, but its quality is not checked and **you alone are fully responsible if the file with the back-up data is easy to decrypt if leaked** (use high-quality unique passwords whenever possible).

**Be aware**: Using different passwords will create different back-ups. At the same time, _using the same password will overwrite the existing back-up_. If a copy of SQLantern is used by multiple users and several of them use the same password to store back-ups on the server, they will overwrite each other's back-ups.\
Hint: You can also store multiple different back-ups on the server under different passwords.

To restore a back-up, use the reverse action - `Restore from server`.

<details>
	<summary>Configuration instructions</summary>
	
Write the following in `php/config.sys.php`:
```
define("SQLANTERN_SERVER_SIDE_BACKUPS_ENABLED", true);
```
OR this in `.htaccess`:
```
SetEnvIf Remote_Addr 254.254.254.254 SQLANTERN_SERVER_SIDE_BACKUPS_ENABLED="true"
# enable for one IP address - replace 254.254.254.254 with your static IP address
```
OR this in `.htaccess`:
```
SetEnv SQLANTERN_SERVER_SIDE_BACKUPS_ENABLED "true"
# enable for everybody, not recommended
```
</details>

## How to connect to other hosts than `localhost`
Out of the box SQLantern tries to connect to `localhost` with the user name specified in the login field. If you use "demo", it will connect as "demo" to "localhost" (which is different from "127.0.0.1", by the way).\
To connect to a different host than `localhost`, add "@" followed by the desired host name.\
(You will have to enable `SQLANTERN_MULTIHOST` to do this.)\
E.g. use login `trunk@one.one.one.one` to connect as user "trunk" to the host "one.one.one.one".\
You can use IP address instead of a host name.\
E.g. use login `branch@192.168.1.254` to connect as user "branch" to the host "192.168.1.254".

If you want to connect to the same non-"localhost" host by default, I recommend to change the `SQLANTERN_DEFAULT_HOST` server-side setting _without enabling_ `SQLANTERN_MULTIHOST`. You also won't need to specify the host in the login after that.\
E.g. if you set `SQLANTERN_DEFAULT_HOST` to "one.one.one.one" and then use login "trunk" (without the host name), SQLantern will connect to the host "one.one.one.one" as "trunk".

<details>
	<summary>Configuration instructions</summary>
	
Write the following in `php/config.sys.php`:
```
define("SQLANTERN_DEFAULT_HOST", "one.one.one.one");
// replace one.one.one.one with the desired host or IP
define("SQLANTERN_MULTIHOST", true);
// globally enabling `SQLANTERN_MULTIHOST` for everybody is NOT RECOMMENDED
```
OR this in `.htaccess`:
```
SetEnvIf Remote_Addr 254.254.254.254 SQLANTERN_DEFAULT_HOST="one.one.one.one"
# change for one IP address - replace 254.254.254.254 with your static IP address
# replace one.one.one.one with the desired host or IP
SetEnvIf Remote_Addr 254.254.254.254 SQLANTERN_MULTIHOST="true"
# it is reasonably secure to enable `SQLANTERN_MULTIHOST` for a static IP address
```
OR this in `.htaccess`:
```
SetEnv SQLANTERN_DEFAULT_HOST "one.one.one.one"
# enable for everybody
SetEnv SQLANTERN_MULTIHOST "true"
# enabling `SQLANTERN_MULTIHOST` for everybody is NOT RECOMMENDED
```
</details>

## How to connect to PostgreSQL
In SQLantern the database driver is defined by the port.\
Out of the box, only standard ports are configured - `3306` for MariaDB/MySQL and `5432` for PostgreSQL.\
It means that if you connect via port `3306`, SQLantern will use MariaDB/MySQL driver (PHP's `mysqli`, to be precise), and if you use port `5432`, SQLantern will use PostgreSQL driver (PHP's `pgsql`).

Given that, you just need to add a hostname and port to the username to connect to PostgreSQL.\
E.g. use login `demo@192.168.1.254:5432` to connect as "demo" to host "192.168.1.254" using PostgreSQL driver.\
(By the way, adding a hostname is required if you need to use a port, even if the hostname is `localhost`.)

If you want to _always_ connect to PostgreSQL without specifying the port in the login (connect to PostgreSQL by default), set the server-side setting `SQLANTERN_DEFAULT_PORT` to "5432".

<details>
	<summary>Set 5432 as default port</summary>

Write the following in `php/config.sys.php`:
```
define("SQLANTERN_DEFAULT_PORT", "5432");
```
OR this in `.htaccess`:
```
SetEnv SQLANTERN_DEFAULT_PORT "5432"
```
</details>

The default connection database is "postgres" (it is required by PostgreSQL).\
If it doesn't work for you, change the `SQLANTERN_POSTGRES_CONNECTION_DATABASE` setting. If you don't know what I'm talking about, you probably don't need to touch it :-)\
(You are expected to know what works in your case, I can't give you any advice, it's individual.)

<details>
	<summary>Configuration instructions</summary>

Write the following in `php/config.sys.php`:
```
define("SQLANTERN_POSTGRES_CONNECTION_DATABASE", "template1");
// don't forget to replace "template1" with the database that works
```
OR this in `.htaccess`:
```
SetEnv SQLANTERN_POSTGRES_CONNECTION_DATABASE "template1"
# don't forget to replace "template1" with the database that works
```
</details>

If you need to use non-standard port, see the "How to use non-standard ports" section in this document.

## How to connect via non-standard ports
Add a setting `SQLANTERN_PORT_{number}` to be able to connect via a port `{number}`.\
The _value_ of the setting must be one of the SQLantern drivers: `mysqli` or `pgsql`.\
E.g. set `SQLANTERN_PORT_13306` to `mysqli` to connect via port 13306 to MariaDB/MySQL.\
Or e.g. set `SQLANTERN_PORT_54320` to `pgsql` to connect via port 54320 to PostgreSQL.

Each port requires a dedicated separate setting. Create as many of those settings as you need.

Out of the box ports 3306 and 5432 are already configured (`SQLANTERN_PORT_3306` is `mysqli` and `SQLANTERN_PORT_5432` is `pgsql`).

<details>
	<summary>Configuration instructions</summary>

Write the following in `php/config.sys.php`:
```
define("SQLANTERN_PORT_13306", "mysqli");
// replace 13306 with a desired port number; the value can be "mysqli" or "pgsql"
```
OR this in `.htaccess`:
```
SetEnv SQLANTERN_PORT_13306 "mysqli"
# replace 13306 with a desired port number; the value can be "mysqli" or "pgsql"
```
</details>

## Protect the single-file version of SQLantern
The single-file version of SQLantern is designed to be dropped into a location and just work. However, taking a couple of small simple steps to protect it can go a long way in keeping your server safe.\
Here are the things I personally always do with the single-file version and which I highly recommend doing:
- Never put SQLantern into the root directory of the website. I usually create a path similar to `/.secret/.not-sqlantern` and put it there :-) Ideally you should invent your own secret path.
- Allow opening that secret directory only from my static IP addresses in `.htaccess`:
	```
	Order Deny,Allow
	Allow from 254.254.254.254
	Deny from all
	# replace 254.254.254.254 with your static IP address
	```
- Rename the single-file SQLantern - it works no matter it's name. I usually put it into a secret directory and rename it to `index.php` (because I protect the whole directory).

If you don't have a static IP address, I advice to at least put SQLantern into some secret directory and/or rename it to something random and unrelated.\
If you don't have a static IP address, but can password-protect the directory with SQLantern, this is also highly recommended. (Look for directory password protection feature in your server management panel, there is usually a user interface to do it.)

## How to configure the single-file version (custom server-side settings in single-file version of SQLantern)
You may want to use the single-file version with non-standard configuration:
- connect to multiple hosts
- connect to one host but not to `localhost`
- connect via non-standard ports
- etc

However, the single-file version doesn't read `config.sys.php`.\
_It DOES read the environment variables_ though and everything can be configured via them (since version 1.9.13).\
Here are examples of adjusting some settings in `.htaccess`:
```
# if you only need to connect to one host, but it is not `localhost`, set a custom `SQLANTERN_DEFAULT_HOST`
# (replace "one.one.one.one" with the desired host or IP)
SetEnv SQLANTERN_DEFAULT_HOST "one.one.one.one"
# if you need to connect to multiple hosts, set `SQLANTERN_MULTIHOST` to true - be careful and responsible with it, please
SetEnv SQLANTERN_MULTIHOST "true"
# use non-standard ports
SetEnv SQLANTERN_PORT_33060 "mysqli"
SetEnv SQLANTERN_PORT_55432 "pgsql"
# connect to the MariaDB/MySQL with SSL
SetEnv SQLANTERN_USE_SSL "true"
```

_Everything_ is configurable this way, you can even enable `SQLANTERN_SERVER_SIDE_BACKUPS_ENABLED` and use server-side back-ups with a single-file SQLantern.

Be mindful and always protect your single-file version, please (as described in the dedicated section in this file).

## Connecting with SSL
When you need to connect to a database server with SSL, set the server-side `SQLANTERN_USE_SSL` setting to `true`.

<details>
	<summary>Configuration instructions</summary>

Write the following in `php/config.sys.php`:
```
define("SQLANTERN_USE_SSL", "true");
```
OR this in `.htaccess`:
```
SetEnv SQLANTERN_USE_SSL "true"
```
</details>

Often times you won't have privileges to add an SSL certificate to the server running SQLantern.\
As a workaround there is an option to __disable SSL validation__ (trust the certificate blindly, without checking it).\
If you are willing to do so, set another server-side `SQLANTERN_TRUST_SSL` setting to `true`.\
**This is not recommended and you should never do that for anything other than testing and debugging purposes.**

<details>
	<summary>Configuration instructions</summary>

Write the following in `php/config.sys.php`:
```
define("SQLANTERN_TRUST_SSL", "true");
```
OR this in `.htaccess`:
```
SetEnv SQLANTERN_TRUST_SSL "true"
```
</details>

Both of those settings currently **only** work for MariaDB/MySQL.

## Display a real error instead of generic "CONNECTION FAILED"
Out of the box SQLantern returns the "CONNECTION FAILED" error without any further details. This is done to confuse potential attackers.\
Usually legitimate users don't need to know what is the real reason of the connection failure, but sometimes they do.\
If you need to see the real connection error, set the server-side `SQLANTERN_SHOW_CONNECTION_ERROR` setting to `true`.

![](https://sqlantern.com/images/en_real_connection_error.png)

<details>
	<summary>Configuration instructions</summary>

Write the following in `php/config.sys.php`:
```
define("SQLANTERN_SHOW_CONNECTION_ERROR", true);
// enable for everybody, not recommended
```
OR this in `.htaccess`:
```
SetEnvIf Remote_Addr 254.254.254.254 SQLANTERN_SHOW_CONNECTION_ERROR=true
# enable for one IP address - replace 254.254.254.254 with your static IP address
```
OR this in `.htaccess`:
```
SetEnv SQLANTERN_SHOW_CONNECTION_ERROR "true"
# enable for everybody, not recommended
```
Make sure your copy of SQLantern is protected by additional means (like IP-filtering or an additional password-protected access) and mischievous users are definitely blocked off when you enable `SQLANTERN_SHOW_CONNECTION_ERROR`.
</details>

## You're getting "Server has gone away" (or "Server not responding") when importing a database dump
The import feature in SQLantern is built on a compromise.\
It takes more time than the alternatives and uses _ungodly_ amounts of server RAM. Is it not by my design, but some internal interaction of PHP with the `mysqli` driver makes it like that.\
The only reason I've ever seen so far for the "Server has gone away" error is: the server runs out of RAM while importing (RAM and swap, to be precise).\
If it works for you - great.\
If it doesn't - I'm sorry.\
There is no fix, unfortunately, and there probably won't be any in the foreseeable future.

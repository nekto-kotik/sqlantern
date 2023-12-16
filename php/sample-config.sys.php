<?php
/*
This file is part of SQLantern Database Manager
Copyright (C) 2022, 2023 Misha Grafski AKA nekto
License: GNU General Public License v3.0
https://github.com/nekto-kotik/sqlantern
https://sqlantern.com/

SQLantern is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
*/

// see `index.php` for the full list of configurable parameters

//define("SQL_ROWS_PER_PAGE", 50);	// uncomment and change for a different default number of rows on page

//define("SQL_DEFAULT_HOST", "127.0.0.1");	// If your default host is not `localhost`, uncomment this line and change it according to your needs. This value can be a remote address/host, like `example.com`!

//define("SQL_SHORTENED_LENGTH", 300);	// uncomment and change the amount of characters, to which long values will be shortened

//define("SQL_DISPLAY_DATABASE_SIZES", true);	// uncomment to display full databases' sizes in database lists, if the slowed down speed is acceptably usable for you

//define("SQL_MULTIHOST", true);	// DON'T SET this to `true` if you're only working with one remote host!!! Change `SQL_DEFAULT_HOST` above instead!
// Beware that you basically invite hackers to use your server for bruteforcing any other server on the internet, if you set `SQL_MULTIHOST` to `true` and don't limit access to your copy of SQLantern somehow (e.g. by IP)!

function customNumberFormat( $n ) {
	// change the number format to your liking, and uncomment the line with `SQL_NUMBER_FORMAT` below
	return number_format($n, 0, ".", ",");
}

//define("SQL_NUMBER_FORMAT", "customNumberFormat");	// uncomment to use `customNumberFormat` to format number of rows and number of pages

//define("SQL_FAST_TABLE_ROWS", false);	// uncomment to see EXACT number of rows in each table in table lists (it's much slower in databases with big numbers of rows); with the `fast` method some table engines return approximate number of rows

//define("SQL_SESSION_NAME", "secrets");	// uncomment and change to better hide the SQLantern-related cookies
//define("SQL_COOKIE_NAME", "mysteries");	// uncomment and change to better hide the SQLantern-related cookies

//
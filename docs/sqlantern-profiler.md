# Query profiling in SQLantern
Query profiling is, in general, not complicated and not special at all, but small features in SQLantern make it versatile: profiling one or multiple queries in a loop, reordering the queries, and skipping some queries.\
Combined with panel duplication and working sessions, rerunning and comparing performance is very comfortable and developer/QA-friendly.

The queries are measured in a loop endlessly, one by one (separately and in succession), until stopped.\
Measurements start when "Run" is pressed and stop when "Stop" is pressed.\
If "Run" is pressed again, _all measurements in this panel are erased_ (the process is restarted; queries are left in place, of course).\
If you need to see both old and new measurements, write the old measurements down somewhere or duplicate the profiler panel and run new measurements in the new panel.

Queries are run and measured _sequentially, not in parallel_.\
To measure queries in parallel, create/duplicate multiple panels with profilers. Because every panel runs on a separate thread, profilers in multiple panels run in parallel.

## Profiling in MariaDB/MySQL
### Where does the MariaDB/MySQL duration come from?
The time you get is "Duration" from `SHOW PROFILES`.\
It's an internal database measurement, unaffected by network or other overheads.

### Internal database cache in MariaDB/MySQL
There are no nuances about internal cache in PostgreSQL, as far as I'm aware (you can only restart the database system; correct me if I'm wrong, please), but MariaDB/MySQL has some.\
I suspect that InnoDB strategy shared further below will work in PostgreSQL, but I haven't tried it there yet.

I find it helpful and important to know both "cold" and cached performance, so I'm describing how to get both for the two most popular table engines.

#### Profiling MyISAM tables
The solution is straightforward with MyISAM table engine: to get both the "cold" and cached time, a query or queries can be run with and without running `FLUSH TABLES` first:

![](https://sqlantern.com/images/en_profiler_flush_tables_on_off.jpg)

But the same task is far less trivial with InnoDB, as there is no command to clear its internal cache (buffer pool).\
The typical advice on the internet is to restart the database system, but I have a different recommendation.\
My idea is multi-step, and there are the more steps the more tables are involved, but it is controllable and easily reproducible from within SQLantern.

#### Profiling InnoDB tables
My strategy for measuring "cold" time with InnoDB tables is to use a _dedicated temporary database_ (e.g. "tmp_profiling"), which is a copy of the original database of interest, and do the following steps _every time, on every measurement_:
- `TRUNCATE` the table or tables involved in my query/queries _in the temporary database_
- Copy the real data into that temporary database (cross-database `INSERT/SELECT`, like on the screenshot below)
- Run the query or queries I want measured "cold"

![](https://sqlantern.com/images/en_profiler_innodb_strats.jpg)

By using a temporary database with the same tables, I can run the original queries I need profiled, I don't have to modify them.

As far as I can see, it works very well, and my results are the same as "cold" runs after a database system restart.\
The illustration above has `TRUNCATE` and `INSERT` executed before every `SELECT` on the left, getting time without cache, and to the right, the cached time is displayed, without running `TRUNCATE` and `INSERT` (they are skipped).

## Profiling in PostgreSQL
### Where does the PostgreSQL duration come from?
The time you get is a **sum** of "Planning Time" _and_ "Execution Time" from the `EXPLAIN (ANALYZE true)` query.\
It's an internal database measurement, unaffected by network or other overheads, _unless the values start with "n/a"_.

If you get values starting with "n/a", don't trust those measurements!\
This means that the query is incompatible with `EXPLAIN` (e.g. `TRUNCATE`) and the measurement was done in PHP and includes network latency and whatever other internal delays/overhead.\
Those measurements are there to give you at least some idea, but you should treat them as very imprecise.

Here's an example with and without "n/a" results:\
![](https://sqlantern.com/images/en_profiler_postgresql_n_a.jpg)
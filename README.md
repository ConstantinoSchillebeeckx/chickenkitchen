# chicken kitchen

A simplified CRUD front-end with built in visualization features.

After not working on this for a long time, I'm doing some house cleaning and coming back to this - I'm trying to figure out how this is generally setup.

It looks like it assumes its running in Wordpress which handles the [user login validation](https://github.com/ConstantinoSchillebeeckx/chickenkitchen/blob/master/functions.php#L2714) - I probably did this out of laziness since I didn't really want to do account validation.

It has the WP pages */add-table* and */view-table*.

## Built on

- [datatables.net](https://datatables.net/)
- [Bootstrap 3](https://getbootstrap.com/css/)
- [jQuery](https://code.jquery.com/)
- [jQUery QueryBuilder](http://querybuilder.js.org/)

## Database setup

- database must be `utf8`
- Each *company* or *account* gets their own database name
- Each table always has a history counterpart which is named the same as table but with an appended '_history'
- Each table must at a minimum have a primary key, it'll be named `_UID int(11)` and must be the first column in the table
- Each table must have the comment {"name":"XXX"} set
- Each history table requires:
  1. same name as data table counterpart, but with appended '_history'
  2. same columns as data table counterpart
  3. additional columns:
    - _UID_fk: int(11), index, foreign key with _UID on data table counterpart and have the comment ` {"column_format": "hidden"}`
    - User:  varchar(128), not null
    - Timestamp: timestamp, default CURRENT_TIMESTAMP
    - Action: varchar(128), not null

## Table options

Fields can have comments formatted as JSON to specify different options:
{"column_format": "hidden"} - will be hidden from view on front end
{"column_format": "date"} - for use with date type fields (stored as datetime) will only display date part on the front end

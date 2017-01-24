# chicken kitchen

A simplified CRUD front-end with built in visualization features.

## Built on

- [Medoo](https://github.com/catfan/Medoo)
- [datatables.net](https://datatables.net/)
- [Bootstrap 3](https://getbootstrap.com/css/)
- [jQuery](https://code.jquery.com/)
- [jQUery QueryBuilder](http://querybuilder.js.org/)


## TODO
- [x] create new table
- [x] delete table
- [x] add item in table
- [x] edit item in table
- [x] delete item in table
- [ ] hyper links for quick cell filtering
- [ ] revert history
- [ ] edit table (fields, name)
- [ ] batch (insert, delete, edit)
- [ ] advanced searching (http://querybuilder.js.org/)


## Database setup

- database must be `utf8`
- Each *company* or *account* gets their own database name
- Each table always has a history counterpart which is named the same as table but with an appended '_history'
- Each table must at a minimum have a primary key, it'll be named `_UID int(11)` and must be the first column in the table
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

Version 1.0-beta2
=================

- Fixed: exponential multiplication of validation errors when running `ezdbintegrity:check:storage`

- Fixed: reduce memory consumption when checking storage

- New: add to the list of missing files the information about the Contents to which they belong when running
  `ezdbintegrity:check:storage --check-db-orphans --display-data -v`

- New: add to the list of orphan files the information about their size and modification date when running
  `ezdbintegrity:check:storage --display-data -v`

Version 1.0-beta1
=================

Initial release

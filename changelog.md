# PHP Github Webhook Change Log

## Version 0.0.6 (February 2nd 2019)
* Added support for multiple whitelisted repos
* Added specific branch whitelist (can only specify 1 branch at the moment)
* Currently removing files will delete the whole folder instead of just the file, will be fixed in version 0.0.7
* If you do not specify a specific branch it will default to the master branch
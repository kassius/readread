# READREAD

A Rapid Serial Visual Presentation PHP script, using ncurses, for quick reading.

![terminator screenshot](screeshot-000.png)

**IT IS NOT READY YET**

## Installing on an Ubuntu 14.04:

```
sudo apt-get install php5-cli php5-dev libncursesw5-dev

sudo pecl install ncurses
```
Add at the end of `/etc/php5/cli/php.ini`

```
extension=ncurses.so
```


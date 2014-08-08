# READREAD

A Rapid Serial Visual Presentation PHP script, using ncurses, for quick reading.

![terminator screenshot](screenshot-000.png)

**IT IS NOT READY YET**

## Installing depedencies on an Ubuntu 14.04:

```shell
sudo apt-get install php5-cli php5-dev libncursesw5-dev ncurses-dev

sudo pecl install ncurses
```
Add at the end of `/etc/php5/cli/php.ini`

```php
extension=ncurses.so
```
**IF THE METHOD ABOVE FAILS**, then maybe you can build and install the extension manually, this way

```shell
mkdir php-ncurses
cd php-ncurses
pecl download ncurses #ignore the error
tar zxvf ncurses-1.0.2.tgz
cd ncurses-1.0.2/
./configure
make
sudo make install
```

## Running the script

```shell
chmod +x readread.php
./readread.php
```


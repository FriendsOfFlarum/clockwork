# FriendsOfFlarum Clockwork

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/clockwork.svg)](https://packagist.org/packages/fof/clockwork) [![OpenCollective](https://img.shields.io/badge/opencollective-fof-blue.svg)](https://opencollective.com/fof/donate) [![Donate](https://img.shields.io/badge/donate-datitisev-important.svg)](https://datitisev.me/donate)

A [Flarum](http://flarum.org) extension. Debug your Flarum forum with [Clockwork](https://underground.works/clockwork/).

[![screenshot](https://i.imgur.com/m55k8Rd.png)](https://imgur.com/a/JCD6Wk4)

### Installation

Use [Bazaar](https://discuss.flarum.org/d/5151-flagrow-bazaar-the-extension-marketplace) or install manually with composer:

```sh
composer require fof/clockwork
```

### Updating

```sh
composer update fof/clockwork
```

### Nginx Config

If you're using the `.nginx.conf` file included with Flarum, include the following above the `location /` block:

```
location ~* /__clockwork/.*\.(css|js|json|png|jpg) {
    try_files /index.php?$query_string /index.php?$query_string;
}
```

### Links

[![OpenCollective](https://img.shields.io/badge/donate-friendsofflarum-44AEE5?style=for-the-badge&logo=open-collective)](https://opencollective.com/fof/donate) [![GitHub](https://img.shields.io/badge/donate-datitisev-ea4aaa?style=for-the-badge&logo=github)](https://datitisev.me/donate/github)

- [Packagist](https://packagist.org/packages/fof/clockwork)
- [GitHub](https://github.com/FriendsOfFlarum/clockwork)

An extension by [FriendsOfFlarum](https://github.com/FriendsOfFlarum).

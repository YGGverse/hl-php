# hl-php

PHP 8 library for Half-Life API with native IPv6 / Yggdrasil support

## Install

`composer require yggverse/hl`

## Usage

### Xash3d

#### Master

```
$master = new \Yggverse\Hl\Xash3D\Master('hl.ygg', 27010);

var_dump(
  $master->getServersIPv6()
);

var_dump(
    $master->getErrors()
);
```

## Projects

* [HLState](https://github.com/YGGverse/HLState) - Web Monitor for Half-Life Servers
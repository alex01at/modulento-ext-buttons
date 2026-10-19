# Buttons

An addon for [Modulento](https://github.com/alex01at/modulento) that adds a
public tool to lay out images in 57mm circles and export a print-ready A4
PDF for a button maker. As an addon, it can be active together with any
other addon and with the installation's one app.

- **`/buttons`**: upload an image, position and zoom it inside a 57mm
  circle, add it with a quantity. Several designs are mixed round-robin
  across the sheet rather than grouped block by block, and the last page
  can optionally be padded with the existing designs instead of left
  partly blank.
- Nothing is stored - no database table, no upload kept beyond the single
  request. The PDF is built with the bundled [FPDF](http://www.fpdf.org)
  library (public domain, `vendor/fpdf.php`).

## Installing

Clone or symlink this repository into `extensions/buttons` of a Modulento
installation, then enable it under Administration → Extensions.

## Running its tests

```
MODULENTO_CORE=/path/to/modulento php tests/run.php
```

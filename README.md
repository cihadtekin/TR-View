# TR-View
Extendable template/view blocks for php.

### Usage
```php
<?php
include 'TR_View.php';
// Provide an autoloader for requested blocks
TR_View::bindLoader(function($blockname) {
	$blockname = str_replace('/', DIRECTORY_SEPARATOR, $blockname);
	$filename = 'views/' . $blockname . '.html';
	if (file_exists($filename)) {
		return file_get_contents($filename); // File found
	} else {
		return FALSE; // File not found, run next loader if there are any
	}
});
// Render a block
$result = TR_View::factory('block')->render();
// Or:
echo TR_View::factory('blockname');
```

### Blocks
```html
{% blockname %}
  Lorem ipsum dolor sit amet
{%/ blockname %}
```

### Basic Inheritance
"> ..." means "extends ..."

base.html content:
```html
{% base %}
  base content
{%/ base %}
```
child.html content:
```html
{% child > base %}
  {% super /%}
  child content
{%/ child %}
```
Result:
```html
  base content
  child content
```

### Overwriting types
baseblock.html content:
```html
{% baseblock %}
  baseblock content
  {% subblock1 %}
    baseblock.subblock1 content
    {% subblock11 %}
      baseblock.subblock1.subblock11 content
    {%/ subblock11 %}
  {%/ subblock1 %}
{%/ baseblock %}
```
***Overwriting all of the content***

childblock.html content:
```html
{% childblock > baseblock %}
  childblock content
  {% super.subblock1.subblock11 /%}
{%/ childblock %}
```
Result:
```html
  childblock content
        baseblock.subblock1.subblock11 content
```

***Overwriting blocks***

  - ">> ..." means "overwrite only ...'s blocks",
  - ">" (without a block name comes after >) means "overwrite nearest extended block's children"

childblock2.html content:
```html
{% childblock2 >> baseblock %}
  childblock2 content
  {% subblock1 > %}
    {% subblock11 %}
      childblock2.subblock1.subblock11 content
    {%/ subblock11 %}
  {%/ subblock1 %}
{%/ childblock2 %}
```
Result:
```html
  baseblock content
    baseblock.subblock1 content
      childblock2.subblock1.subblock11 content
```

### Including Blocks

block1.html content:
```html
{% block1 %}
  block1 content
  {% block11 %}
    block1.block11 content
  {%/ block11 %}
{%/ block1 %}
```
block2.html content:
```html
{% block2 %}
  block 2 content
  {% block1.block11 /%}
{%/ block2 %}
```
Result:
```html
  block 2 content
    block1.block11 content
```

### Chaining

baseblock.html content:
```html
{% baseblock %}
  baseblock content
  {% subblock1 %}
    baseblock.subblock1 content
    {% subblock11 %}
      baseblock.subblock1.subblock11 content
    {%/ subblock11 %}
  {%/ subblock1 %}
{%/ baseblock %}
```
childblock.html content:
```html
{% childblock >> baseblock.html %}
  childblock content
  {% subblock1.subblock11 %}
    childblock.subblock1.subblock11 content
    {% super.subblock1.subblock11/ %}
  {% /subblock1.subblock11 %}
{%/ childblock %}
```
Result:
```html
  baseblock content
    baseblock.subblock1 content
    childblock.subblock1.subblock11 content
      baseblock.subblock1.subblock11 content
```

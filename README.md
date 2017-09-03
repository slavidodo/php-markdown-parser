# php-markdown-parser
----


## Usage
Usage is so simple, include the file containing the base code, and just define a a markdown parser.
```php

$text = '# Header
### sub header

## Complaint 1.
---
I am a **strong** text.
';

$markdown = new magictr\Markdown(["outWrapper" => true]);

echo $markdown->parse($text);
```

## Options
### gfm
Type: `boolean`  
Default: `true`

Enable [GitHub flavored markdown][gfm].

### tables
Type: `boolean`  
Default: `true`

Enable GFM [tables][tables].
This option requires the `gfm` option to be true.

### breaks
Type: `boolean`  
Default: `false`

Enable GFM [line breaks][breaks].
This option requires the `gfm` option to be true.

### pedantic
Type: `boolean`  
Default: `false`

Conform to obscure parts of `markdown.pl` as much as possible. Don't fix any of
the original markdown bugs or poor behavior.

### sanitize

Type: `boolean`  
Default: `false`

Sanitize the output. Ignore any HTML that has been input.

### smartLists

Type: `boolean`  
Default: `true`

Use smarter list behavior than the original markdown. May eventually be
default with the old behavior moved into `pedantic`.

### smartypants
Type: `boolean`  
Default: `false`

Use "smart" typograhic punctuation for things like quotes and dashes.

### xhtml
Type: `boolean`  
Default: `false`

Parse Html tags

### outWrapper
Type: `boolean`  
Default: `false`

shadow the result to another html root.

### highlighter (not supported)
Type: `instance` => contains the function `highlight`, takes args (lang **xml, php, ..etc**, code)
Default: `null`

highlights codes

### emotions
Type: `boolean`  
Default: `false`

Allows the usage of emotions

## emotionClass
Type: `string`  
Default: ` `

append that class to emotions. Only works if emotions is enabled

## emotionDirectory
Type: `string`  
Default: ` `

where emotions' images are added. Only works if emotions is enabled

### emotionList
Type: `list`  
Default: `[]`

a list of available emotions

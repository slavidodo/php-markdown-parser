<?php

/**
 * A simple markdown parser for server-side nerds.
 *
 * author: <slavidodo at gmail dot com>
 */

namespace magictr {
 
class MarkdownRenderer
{
	public $options;
	public $highlighter;
	private $rules;
	
	public function __construct($options)
	{
		$this->options = $options;
		$this->highlighter = $this->options["highlighter"];

		// another copy of inline rules!
		$this->rules = [
			"strong" => '/__([\s\S]+?)__(?!_)|^\*\*([\s\S]+?)\*\*(?!\*)/',
			"em" => '/\b_((?:[^_]|__)+?)_\b|^\*((?:\*\*|[\s\S])+?)\*(?!\*)/',
			"code" => '/(`+)\s*([\s\S]*?[^`])\s*\1(?!`)/',
			"del" => '/a^/',
			"emotion" => '/:([\s\S]+?):/',
			"icon" => '/::([\s\S]+?)::/',
			"tag" => '/<([a-zA-Z][A-Z0-9]*)\b[^>]*>(.*?)<\/\1>/',
		];
		
		if ($this->options["gfm"]) {
			$this->rules["del"] = '/~~(?=\S)([\s\S]*?\S)~~/';
		}
	}
	
	public function code($code, $lang, $escaped) {
		if ($this->highlighter) {
			$out = $highlighter->highlight($code, $lang);
			if ($out != null && $out !== $code) {
				$escaped = true;
				$code = $out;
			}
		}

		$code = implode("<br>", explode(PHP_EOL, $escaped ? $code : Markdown::escape($code, true)));
		if (!$lang || strlen($lang) == 0) {
			return '<div style="position: relative"><pre><code>'
				. $code
				. '</code></pre></div>'
			;
		}
		
		return '<div style="position: relative"><pre class="' . $this->options["langPrefix"] . Markdown::escape($lang, true) . '" ><code class="'
			. $this->options["langPrefix"]
			. Markdown::escape($lang, true)
			. '">'
			. $code
			. '</code></pre></div>'
		;
	}
	public function blockquote($quote) {
		return '<blockquote>' . $quote . '</blockquote>';
	}
	public function html($html) {
		return $html;
	}
	public function heading($text, $level, $raw) {
		$fixed_text = preg_replace('/^\$([\s\S]+?)\$/', '', $raw); // replacing symbols with NONE
		$fixed_text = preg_replace($this->rules["emotion"], '', $fixed_text);
		$fixed_text = preg_replace($this->rules["icon"], '', $fixed_text);
		$fixed_text = preg_replace($this->rules["strong"], '$1$2', $fixed_text);
		$fixed_text = preg_replace($this->rules["em"], '$1$2', $fixed_text);
		$fixed_text = preg_replace($this->rules["del"], '$1', $fixed_text);
		$fixed_text = preg_replace($this->rules["tag"], '', $fixed_text);
		
		return '<h' . $level
			. ' id="' . $this->options["headerPrefix"] . trim(preg_replace('/ /', '_', preg_replace('/[^\w(?= )]+/', '', strtolower(trim($fixed_text)))))
			. '">'
			. trim($text)
			. '</h' . $level . '>';
	}
	public function hr() {
		return $this->options["xhtml"] ? '<hr />' : '<hr>';
	}
	public function list($body, $ordered) {
		$type = $ordered ? 'ol' : 'ul';
		return '<' . $type . '>' . $body . '</' . $type . '>';
	}
	public function listitem($item){
		return '<li>' . $item . '</li>';
	}
	public function paragraph($text) {
		return '<p>' . trim($text) . '</p>';
	}
	public function table($header, $body) {
		return '<table>'
			. '<thead>'
			. $header
			. '</thead>'
			. '<tbody>'
			. $body
			. '</tbody>'
			. '</table>';
	}
	public function tablerow($content) {
		return '<tr>' . $content . '</tr>';
	}
	public function tablecell($content, $flags) {
		$type = array_key_exists("header", $flags) && $flags["header"] ? 'th' : 'td';
		$tag = array_key_exists("align", $flags) && $flags["align"] != null
			? '<' . $type . ' style="text-align:' . $flags["align"] . '">'
			: '<' . $type . '>';
		return $tag . trim($content) . '</' . $type . '>';
	}
	public function strong($text) {
		return '<strong>' . $text . '</strong>';
	}
	public function em($text) {
		return '<em>' . $text . '</em>';
	}
	public function codespan($text) {
		return '<code>' . $text . '</code>';
	}
	public function br() {
		return $this->options["xhtml"] ? '<br/>' : '<br>';
	}
	public function del($text) {
		return '<del>' . $text . '</del>';
	}
	public function link($href, $title, $text) {
		if ($this->options["sanitize"]) {
			$prot = strtolower(preg_replace('/[^\w:]/g', "", urldecode(Markdown::unescape($text))));
			if (strpos($prot, 'javascript:') === 0 || strpos($prot, 'vbscript:') === 0) {
				return "";
			}
		}
		
		$out = '<a target="_blank" href="' . $href . '"';
		if ($title) {
			$out .= ' title="' . $title . '"';
		}
		
		$out .= '>' . $text . '</a>';
		return $out;
	}
	public function image($href, $title, $text) {
		$text = preg_replace($options_re, "", $text);
		
		$out = '<img src="' . $href . '" alt="' . $text . '"';
		if ($title) {
			$out .= ' title="' . $title . '"';
		}
		
		$out .= $this->options["xhtml"] ? '/>' : '>';
		
		$wrapper = '<div>' . $out . '</div>';
		return $wrapper;
	}
	public function text($text) {
		return $text;
	}
	public function icon($class) {
		return '<i class="' . trim($class) . '"> </i>';
	}
	public function emotion($name, $class, $directory) {
		$out = '<img class="' . $this->options["emotionClass"] . '" src="'
			. $this->options["emotionDirectory"] . strtolower($name)
			. '" alt="' . strtolower($name) . '"'
			. ($this->options["xhtml"] ? '/>' : '>');
		return $out;
	}
}

class MarkdownParser
{
	private $options;
	private $inline;
	private $renderer;
	private $tokens;
	private $token;
	
	public function __construct($options)
	{
		$this->options = $options;
		$this->renderer = $this->options["renderer"];
	}
	
	public function next()
	{
		return $this->token = array_pop($this->tokens);
	}
	
	public function peek()
	{
		$key = count($this->tokens) - 1;
		return array_key_exists($key, $this->tokens) ? $this->tokens[$key] : 0;
	}
	
	public function parseText()
	{
		$body = $this->token["text"];
		
		while ($this->peek()['type'] === 'text') {
			$body .= $this->next()["text"];
		}
		
		return $this->inline->output($body);
	}
	
	public function parse($src)
	{
		$this->inline = new MarkdownInlineLexer($src["links"], $this->options);
		$this->tokens = array_reverse($src["tokens"]);
		
		$out = '';
		while ($this->next()) {
			$out .= $this->tok();
		}
		
		return $out;
	}
	
	public function tok()
	{
		switch ($this->token["type"]) {
			case 'space': {
				return '';
			}
			
			case 'hr': {
				return $this->renderer->hr();
			}
			
			case 'heading': {
				return $this->renderer->heading(
					$this->inline->output($this->token["text"]),
					$this->token["depth"],
					$this->token["text"]
				);
			}
			
			case 'code': {
				return $this->renderer->code(
					$this->token["text"],
					array_key_exists("lang", $this->token) ? $this->token["lang"] : null,
					array_key_exists("escaped", $this->token) ? $this->token["escaped"] : null
				);
			}
			
			case 'table': {
				$header = '';
				$cell = '';
				$body = '';
				
				for ($i = 0; $i < count($this->token["header"]); $i++) {
					$flags = [ "header" => true, "align" => $this->token["align"][$i] ];
					$cell .= $this->renderer->tablecell(
						$this->inline->output($this->token["header"][$i]),
						[ "header" => true, "align" => array_key_exists($i, $this->token["align"]) ? $this->token["align"][$i] : null ]
					);
				}
				
				$header .= $this->renderer->tablerow($cell);
				
				for ($i = 0; $i < count($this->token["cells"]); $i++) {
					$row = $this->token["cells"][$i];
					$cell = '';
					
					for ($j = 0; $j < count($row); $j++) {
						$cell .= $this->renderer->tablecell(
							$this->inline->output($row[$j]),
							[ "header" => false, "align" => array_key_exists($j, $this->token["align"]) ? $this->token["align"][$j] : null ]
						);
					}
					
					$body .= $this->renderer->tablerow($cell);
				}
				
				return $this->renderer->table($header, $body);
			}
			
			case 'blockquote_start': {
				$body = '';
				
				while ($this->next()["type"] !== 'blockquote_end') {
					$body .= $this->tok();
				}
				
				return $this->renderer->blockquote($body);
			}
			
			case 'list_start': {
				$body = '';
				$ordered = $this->token["ordered"];
				
				while ($this->next()["type"] !== 'list_end') {
					$body .= $this->tok();
				}
				
				return $this->renderer->list($body, $ordered);
			}
			
			case 'list_item_start': {
				$body = '';
				
				while ($this->next()["type"] !== 'list_item_end') {
					$body .= $this->token["type"] === 'text'
						? $this->parseText()
						: $this->tok()
					;
				}
				
				return $this->renderer->listitem($body);
			}
			
			case 'loose_item_start': {
				$body = '';
				
				while ($this->next()["type"] !== 'list_item_end') {
					$body .= $this->tok();
				}
				
				return $this->renderer->listitem($body);
			}
			
			case 'html': {
				$html = !$this->token["pre"] && !$this->options["pedantic"]
					? $this->output($this->token["text"])
					: $this->token["text"]
				;
				return $this->renderer->html($html);
			}
			
			case 'paragraph': {
				return $this->renderer->paragraph($this->inline->output($this->token["text"]));
			}
			
			case 'text': {
				return $this->renderer->paragraph($this->parseText());
			}
		}
	}
}

class MarkdownInlineLexer
{
	private $renderer = null;
	private $links = array();
	private $rules = array();
	private $inLink = false;
	
	public function __construct($links, $options)
	{
		$this->options = $options;
		$this->links = is_array($links) ? $links : [];
		$this->renderer = $this->options["renderer"];

		unset($options["renderer"]);
		$this->renderer->options = $options;
		
		$this->rules = MarkdownInline::Normal();
		
		if ($this->options["gfm"]) {
			if ($this->options["breaks"]) {
				$this->rules = MarkdownInline::Breaks();
			} else {
				$this->rules = MarkdownInline::GFM();
			}
		} elseif ($this->options["pedantic"]) {
			$this->rules = MarkdownInline::Pedantic();
		}
	}
	
	private function mangle($text)
	{
		if (!$this->options["mangle"]) {
			return $text;
		}
		
		$out = '';
		$length = strlen($text);
		
		for ($i = 0; $i < $length; $i++) {
			$ch = ord($text{i});
			if ((float)rand()/(float)getrandmax()) {
				$ch = 'x' . dechex($ch);
			}
			
			$out .= '&#' . $ch . ';';
		}
		
		return $out;
	}
	
	private function outputLink($cap, $link)
	{
		$href = Markdown::escape($link["href"]);
		$title = array_key_exists("title", $link) ? Markdown::escape($link["title"]) : null;
		
		return $cap[0]{0} !== '!'
			? $this->renderer->link($href, $title, $this->output($cap[1]))
			: $this->renderer->image($href, $title, Markdown::escape($cap[1]))
		;
	}
	
	private function smartypants($text)
	{
		if (!$this->options["smartypants"]) return $text;
		
		$text = Markdown::upreg_replace('/---/', '\u2014', $text);
		$text = Markdown::upreg_replace('/--/', '\u2013', $text);
		$text = Markdown::upreg_replace('/(^|[-\x{2014}\/(\[{"\s])\'/u', '$1\u2018', $text);
		$text = Markdown::upreg_replace('/\'/', '\u2019', $text);
		$text = Markdown::upreg_replace('/(^|[-\x{2014}\/(\[{\x{2018}\s])"/u', '$1\u201c', $text);
		$text = Markdown::upreg_replace('/"/', '\u201d', $text);
		$text = Markdown::upreg_replace('/\.{3}/', '\u2026', $text);

		return $text;
	}
	
	public function isEmotion($emotion)
	{
		return array_search($emotion, $this->options["emotionList"]) != null;
	}
	
	public function output($src)
	{
		$out = "";
		
		while ($src && strlen($src) > 0) {
			// Escape
			if (preg_match($this->rules["escape"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$out .= $cap[1];
				continue;
			}
			
			// autolink
			if (preg_match($this->rules["autolink"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				if ($cap[2] === '@') {
					$text = $cap[1]{6} === ':'
						? $this->mangle(Markdown::substring($cap[1], 7))
						: $this->mangle($cap[1]);
					;
					$href = $this->mangle('mailto:') . $text;
				} else {
					$text = Markdown::escape($cap[1]);
					$href = $text;
				}
				
				$out .= $this->renderer->link($href, null, $text);
			}
			
			// url (gfm)
			if ($this->inLink && $this->rules["url"] && preg_match($this->rules["url"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$text = Markdown::escape($cap[1]);
				$href = $text;
				$out .= $this->renderer->link($text, null, $href);
			}
			
			// tag
			if (preg_match($this->rules["tag"], $src, $cap)) {
				if (!$this->inLink && preg_match('/^<a /i', $cap[0])) {
					$this->inLink = true;
				} else if ($this->inLink && preg_match('/^<\/a>/i', $cap[0])) {
					$this->inLink = false;
				}
				
				$src = Markdown::substring($src, strlen($cap[0]));
				$out .= $this->options["sanitize"]
					? $this->options["sanitizer"]
						? $this->options["sanitizer"]($cap[0])
						: Markdown::escape($cap[0])
					: $cap[0]
				;
				continue;
			}
			
			// link
			if (preg_match($this->rules["link"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$this->inLink = true;
				$out .= $this->outputLink($cap, [
					"href" => $cap[2],
					"title" => array_key_exists(3, $cap)
						? $cap[3]
						: null
				]);
				$this->inLink = false;
				continue;
			}
			
			// reflink, nolink
			if (preg_match($this->rules["reflink"], $src, $cap) || preg_match($this->rules["nolink"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$link = preg_replace('/\s+/', ' ', array_key_exists(2, $cap) ? $cap[2] : $cap[1]);
				$link = array_key_exists($link, $this->links) ? $this->links[$link] : null;
				if (!$link || !array_key_exists("href", $link)) {
					$out .= $cap[0]{0};
					$src = Markdown::substring($cap[0], 1) . $src;
					continue;
				}
				
				$this->inLink = true;
				$out .= $this->outputLink($cap, $link);
				$this->inLink = false;
			}
			
			// strong
			if (preg_match($this->rules["strong"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				
				if (count($cap) > 2) {
					$out .= $this->renderer->strong($this->output($cap[2]));
				} else {
					$out .= $this->renderer->strong($this->output($cap[1]));
				}
				
				continue;
			}
			
			// em
			if (preg_match($this->rules["em"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				if (count($cap) > 2) {
					$out .= $this->renderer->em($this->output($cap[2]));
				} else {
					$out .= $this->renderer->em($this->output($cap[1]));
				}
				continue;
			}
			
			// code
			if (preg_match($this->rules["code"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$out .= $this->renderer->codespan($this->output(Markdown::escape($cap[2], true)));
				continue;
			}
			
			// icon
			if (preg_match($this->rules["icon"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$out .= $this->renderer->icon($cap[1]);
				continue;
			}
			
			// emotion
			if ($this->options["emotions"] && preg_match($this->rules["emotion"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				
				// avoid bad images!
				if ($this->isEmotion($cap[1])) {
					$out .= $this->renderer->emotion($cap[1]);
				}
				continue;
			}
			
			// br
			if (preg_match($this->rules["br"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$out .= $this->renderer->br();
				continue;
			}
			
			// del
			if ($this->rules["del"] && preg_match($this->rules["del"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$out .= $this->renderer->del($this->output($cap[1]));
				continue;
			}
			
			// text
			if (preg_match($this->rules["text"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$out .= $this->renderer->text(Markdown::escape($this->smartypants($cap[0])));
				continue;
			}
	
			if ($src && strlen($src) > 0) {
				echo 'Infinite loop on byte: ' . $src{0};
				break;
			}
		}
		
		return $out;
	}
}

class MarkdownLexer
{
	private $options;
	private $tokens;
	private $token;
	private $links;
	
	public function __construct($options)
	{
		$this->options = $options;
		$this->rules = MarkdownBlock::Normal();
		
		if ($this->options["gfm"]) {
			if ($this->options["tables"]) {
				$this->rules = MarkdownBlock::Tables();
			} else {
				$this->rules = MarkdownBlock::GFM();
			}
		}
	}

	public function lex($text)
	{
		$text = preg_replace('/\r\n|\r/', "\n",
			preg_replace('/\t/', '    ',
			preg_replace("/\x{00a0}/u", '',
			preg_replace("/\x{2424}/u", "\n", $text
		))));
		return $this->token($text, true);
	}
	
	private function token($src, $top = false, $bq = null)
	{
		$src = preg_replace('/^ +$/m', '', $src);
		
		while ($src && strlen($src) > 0) {
			// new line
			if (preg_match($this->rules["newline"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				if (strlen($cap[0]) > 1) {
					$this->tokens[] = [
						"type" => "space"
					];
				}
			}
			
			// code
			if (preg_match($this->rules["code"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$cap = preg_replace('/^ {4}/m', '', $cap[0]);
				$this->tokens[] = [
					"type" => "code",
					"text" => $this->options["pedantic"]
						? preg_replace('/\n+$/', '', $cap)
						: $cap
				];
				continue;
			}
			
			// fences (gfm)
			if (preg_match($this->rules["fences"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$this->tokens[] = [
					"type" => "code",
					"lang" => $cap[2],
					"text" => array_key_exists(3, $cap)
						? $cap[3]
						: ''
				];
				continue;
			}
			
			// heading
			if (preg_match($this->rules["heading"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$this->tokens[] = [
					"type" => "heading",
					"depth" => strlen($cap[1]),
					"text" => $cap[2]
				];
				continue;
			}
			
			// table no leading pipe (gfm)
			if ($top && preg_match($this->rules["nptable"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$item = [
					"type" => 'table',
					"header" => preg_split('/ *\| */', preg_replace('/^ *| *\| *$/', '', $cap[1])),
					"align" => preg_split('/ *\| */', preg_replace('/^ *|\| *$/', '', $cap[2])),
					"cells" => explode(PHP_EOL, preg_replace('/\n$/', '', $cap[3]))
				];
				
				for ($i = 0; $i < count($item["align"]); $i++) {
					if (preg_match('/^ *-+: *$/', $item["align"][$i])) {
						$item["align"][$i] = 'right';
					} elseif (preg_match('/^ *:-+: *$/', $item["align"][$i])) {
						$item["align"][$i] = 'center';
					} elseif (preg_match('/^ *:-+ *$/', $item["align"][$i])) {
						$item["align"][$i] = 'left';
					} else {
						$item["align"][$i] = null;
					}
				}
				
				for ($i = 0; $i < count($item["cells"]); $i++) {
					$item["cells"][$i] = preg_split('/ *\| */', $item["cells"][$i]);
				}
				
				$this->tokens[] = $item;
				continue;
			}
			
			// lheading
			if (preg_match($this->rules["lheading"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$this->tokens[] = [
					"type" => "heading",
					"depth" => $cap[2] === '=' ? 1 : 2,
					"text" => $cap[1]
				];
				continue;
			}
			
			// hr
			if (preg_match($this->rules["hr"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$this->tokens[] = [
					"type" => 'hr'
				];
				continue;
			}
			// blockquote
			if (preg_match($this->rules["blockquote"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				
				$this->tokens[] = [
					"type" => "blockquote_start"
				];
				
				$cap = preg_replace('/^ *> ?/m', '', $cap[0]);
				$this->token($cap, $top, true);
				
				$this->tokens[] = [
					"type" => "blockquote_end"
				];
				
				continue;
			}
			// list
			if (preg_match($this->rules["list"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				
				$bull = $cap[2];
				$this->tokens[] = [
					"type" => 'list_start',
					"ordered" => strlen($bull) > 1
				];
					
				preg_match_all($this->rules["item"], $cap[0], $cap);
				$cap = $cap[0];
				
				$next = false;
				$l = count($cap);
				$i = 0;
				for (; $i < $l; $i++) {
					$item = $cap[$i];
					
					$space = count($item);
					$item = preg_replace('/^ *([*+-]|\d+\.) +/', '', $item);
					if (strpos($item, PHP_EOL . ' ')) {
						$space -= strlen($item);
						$item = !$this->options["pedantic"]
							? preg_replace('/^ {1,' . $space . '}/m', '', $item)
							: preg_replace('/^ {1,4}/m', '', $item)
						;
					}
					
					if ($this->options["smartLists"] && $i !== $l - 1) {
						preg_match($this->rules["bullet"], $cap[$i + 1], $b);
						$b = $b[0];
						
						if ($bull !== $b && !(strlen($bull) > 1 && strlen($b) > 1)) {
							$src = join(PHP_EOL, array_slice($cap, $i + 1)) . $src;
							$i = $l - 1;
						}
					}
					
					$loose = $next || preg_match('/\n\n(?!\s*$)/', $item);
					if ($i !== $l - 1) {
						$next = $item{strlen($item) - 1} === PHP_EOL;
						if (!$loose) $loose = $next;
					}
					
					$this->tokens[] = [
						"type" => $loose
							? 'loose_item_start'
							: 'list_item_start'
					];
					
					// Recurse.
					$this->token($item, false, $bq);
					
					$this->tokens[] = [
						"type" => 'list_item_end'
					];
				}
				
				$this->tokens[] = [
					"type" => 'list_end'
				];
				continue;
			}
			
			// html (can disable)
			if (preg_match($this->rules["html"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$this->tokens[] = [
					"type" => $this->options["sanitize"]
						? "paragraph"
						: "html",
					"pre" => $this->options["sanitize"] && ($cap[1] === 'pre' || $cap[1] === 'script' || $cap[1] === 'style'),
					"text" => $cap[0]
				];
				continue;
			}
			
			// def
			if (!$bq && $top && preg_match($this->rules["def"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$this->links = [
					"text" => $cap[1],
					"href" => $cap[2],
				];
				continue;
			}
			
			// table (gfm)
			if ($top && preg_match($this->rules["table"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$item = [
					"type" => 'table',
					"header" => preg_split('/ *\| */', preg_replace('/^ *| *\| *$/', '', $cap[1])),
					"align" => preg_split('/ *\| */', preg_replace('/^ *|\| *$/', '', $cap[2])),
					"cells" => explode(PHP_EOL, preg_replace('/\n$/', '', $cap[3]))
				];
				
				for ($i = 0; $i < count($item["align"]); $i++) {
					if (preg_match('/^ *-+: *$/', $item["align"][$i])) {
						$item["align"][$i] = 'right';
					} elseif (preg_match('/^ *:-+: *$/', $item["align"][$i])) {
						$item["align"][$i] = 'center';
					} elseif (preg_match('/^ *:-+ *$/', $item["align"][$i])) {
						$item["align"][$i] = 'left';
					} else {
						$item["align"][$i] = null;
					}
				}
				
				for ($i = 0; $i < count($item["cells"]); $i++) {
					$item["cells"][$i] = preg_split('/ *\| */', preg_replace('/^ *\| *| *\| *$/', '', $item["cells"][$i]));
				}
				
				$this->tokens[] = $item;
				continue;
			}
			
			// top-level paragraph
			if ($top && preg_match($this->rules["paragraph"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$this->tokens[] = [
					"type" => "paragraph",
					"text" => $cap[1]{count($cap[1]) - 1} === '\n'
						? substr($cap[1], 0, -1)
						: $cap[1]
				];
				 continue;
			}
			
			// text
			if (preg_match($this->rules["text"], $src, $cap)) {
				$src = Markdown::substring($src, strlen($cap[0]));
				$this->tokens[] = [
					"type" => "text",
					"text" => $cap[0]
				];
				continue;
			}
			
			if ($src && strlen($src) > 0) {
				echo 'Infinite loop on byte: ' . $src{0};
				break;
			}
		}
		
		return [
			"tokens" => $this->tokens,
			"links" => $this->links
		];
	}
}

class MarkdownInline
{
	public static function Inline()
	{
		return [
			"escape" => '/^\\\\([\\\\`*{}\[\]()#+\-.!_>])/',
			"autolink" => '/^<([^ >]+(@|:\/)[^ >]+)>/',
			"url" => '/a^/', // null
			"tag" => '/^<!--[\s\S]*?-->|^<\/?\w+(?:"[^"]*"|\'[^\']*\'|[^\'">])*?>/',
			"link" => '/^!?\[((?:\[[^\]]*\]|[^\[\]]|\](?=[^\[]*\]))*)\]\(\s*<?([\s\S]*?)>?(?:\s+[\'"]([\s\S]*?)[\'"])?\s*\)/',
			"reflink" => '/^!?\[((?:\[[^\]]*\]|[^\[\]]|\](?=[^\[]*\]))*)\]\s*\[([^\]]*)\]/',
			"nolink" => '/^!?\[((?:\[[^\]]*\]|[^\[\]])*)\]/',
			"strong" => '/^__([\s\S]+?)__(?!_)|^\*\*([\s\S]+?)\*\*(?!\*)/',
			"em" => '/^\b_((?:[^_]|__)+?)_\b|^\*((?:\*\*|[\s\S])+?)\*(?!\*)/',			
			"code" => '/^(`+)\s*([\s\S]*?[^`])\s*\1(?!`)/',
			"br" => '/^ {2,}\n(?!\s*$)/',
			"del" => '/a^/', // null
			"emotion" => '/^:([\s\S]+?):/',
			"icon" => '/^::([\s\S]+?)::/',
			"text" => '/^[\s\S]+?(?=[\\<!\[_*`]| {2,}\n|#|\$|@|:{1,}|$)/',
		];
	}

	public static function Normal()
	{
		return self::Inline();
	}

	public static function GFM()
	{
		return array_merge(self::Normal(), [
			"escape" => '/^\\\\([\\\\`*{}\[\]()#+\-.!_>~|])/',
			"url" => '/^(https?:\/\/[^\s<]+[^<.,:;"\')\]\s])/',
			"del" => '/^~~(?=\S)([\s\S]*?\S)~~/',
			"text" => str_replace('|', '|https?://|', '/^[\s\S]+?(?=[\\<!\[_*`~]| {2,}\n|#|\$|@|:{1,}|$)/'),
		]);
	}

	public static function Breaks()
	{
		return array_merge(self::Normal(), [
			"br" => '/^ *\n(?!\s*$)/',
			"text" => '/^[\s\S]+?(?=[\\<!\[_*`]| *\n|#|\$|@|:{1,}|$)/'
		]);
	}
	
	public static function Pedantic()
	{
		return array_merge(self::Normal(), [
			"strong" => '/^__(?=\S)([\s\S]*?\S)__(?!_)|^\*\*(?=\S)([\s\S]*?\S)\*\*(?!\*)/',
			"em" => '/^_(?=\S)([\s\S]*?\S)_(?!_)|^\*(?=\S)([\s\S]*?\S)\*(?!\*)/'
		]);
	}
}

class MarkdownBlock
{
	private static function Block()
	{
		$block = [
			"newline" => '/^\n+/',
			"code" => '/^( {4}[^\n]+\n*)+/',
			"fences" => '/a^/', // null
			"hr" => '/^( *[-*_]){3,} *(?:\n+|$)/',
			"heading" => '/^ *(#{1,6}) *([^\n]+?) *#* *(?:\n+|$)/',
			"nptable" => '/a^/', // null
			"lheading" => '/^([^\n]+)\n *(=|-){2,} *(?:\n+|$)/',
			"blockquote" => '/^( *&gt;[^\n]+(\n(?! *\[([^\]]+)\]: *<!--?([^\s-->]+)&gt;?(?: +["(]([^\n]+)[")])? *(?:\n+|$))[^\n]+)*\n*)+/',
			"list" => '/^( *)((?:[*+-]|\d+\.)) [\s\S]+?(?:\n+(?=\1?(?:[-*_] *){3,}(?:\n+|$))|\n+(?= *\[([^\]]+)\]: *<!--?([^\s-->]+)&gt;?(?: +["(]([^\n]+)[")])? *(?:\n+|$))|\n{2,}(?! )(?!\1(?:[*+-]|\d+\.) )\n*|\s*$)/',
			"html" => '/^ *(?:<!--[\s\S]*?--> *(?:\n|\s*$)|&lt;((?!(?:a|em|strong|small|s|cite|q|dfn|abbr|data|time|code|var|samp|kbd|sub|sup|i|b|u|mark|ruby|rt|rp|bdi|bdo|span|br|wbr|ins|del|img)\b)\w+(?!:\/|[^\w\s@]*@)\b)[\s\S]+?&lt;\/\1&gt; *(?:\n{2,}|\s*$)|&lt;(?!(?:a|em|strong|small|s|cite|q|dfn|abbr|data|time|code|var|samp|kbd|sub|sup|i|b|u|mark|ruby|rt|rp|bdi|bdo|span|br|wbr|ins|del|img)\b)\w+(?!:\/|[^\w\s@]*@)\b(?:"[^"]*"|\'[^\']*\'|[^\'"&gt;])*?&gt; *(?:\n{2,}|\s*$))/',
			"def" => '/^ *\[([^\]]+)\]: *<?([^\s>]+)>?(?: +["(]([^\n]+)[")])? *(?:\n+|$)/',
			"table" => '/a^/', // null
			"paragraph" => '/^((?:[^\n]+\n?(?!( *[-*_]){3,} *(?:\n+|$)| *(#{1,6}) *([^\n]+?) *#* *(?:\n+|$)|([^\n]+)\n *(=|-){2,} *(?:\n+|$)|( *&gt;[^\n]+(\n(?! *\[([^\]]+)\]: *<!--?([^\s-->]+)&gt;?(?: +["(]([^\n]+)[")])? *(?:\n+|$))[^\n]+)*\n*)+|&lt;(?!(?:a|em|strong|small|s|cite|q|dfn|abbr|data|time|code|var|samp|kbd|sub|sup|i|b|u|mark|ruby|rt|rp|bdi|bdo|span|br|wbr|ins|del|img)\b)\w+(?!:\/|[^\w\s@]*@)\b| *\[([^\]]+)\]: *<!--?([^\s-->]+)&gt;?(?: +["(]([^\n]+)[")])? *(?:\n+|$)))+)\n*/',
			"text" => '/^[^\n]+/',
			"bullet" => '/(?:[*+-]|\d+\.)/',
		];
		
		$bullet = '(?:[*+-]|\d+\.)';
		$block["item"] = str_replace("bull", $bullet, '/^( *)(bull) [^\n]*(?:\n(?!\1bull )[^\n]*)*/m');
		
		return $block;
	}

	public static function Normal()
	{
		return self::Block();
	}

	public static function GFM()
	{
		return array_merge(self::Normal(), [
			"fences" => '/^ *(`{3,}|~{3,})[ \.]*(\S+)? *\n([\s\S]*?)\s*\1 *(?:\n+|$)/',
			"paragraph" => '/^((?:[^\n]+\n?(?! *(`{3,}|~{3,})[ \.]*(\S+)? *\n([\s\S]*?)\s*\2 *(?:\n+|$)|( *)((?:[*+-]|\d+\.)) [\s\S]+?(?:\n+(?=\3?(?:[-*_] *){3,}(?:\n+|$))|\n+(?= *\[([^\]]+)\]: *<!--?([^\s-->]+)&gt;?(?: +["(]([^\n]+)[")])? *(?:\n+|$))|\n{2,}(?! )(?!\1(?:[*+-]|\d+\.) )\n*|\s*$)|( *[-*_]){3,} *(?:\n+|$)| *(#{1,6}) *([^\n]+?) *#* *(?:\n+|$)|([^\n]+)\n *(=|-){2,} *(?:\n+|$)|( *&gt;[^\n]+(\n(?! *\[([^\]]+)\]: *<!--?([^\s-->]+)&gt;?(?: +["(]([^\n]+)[")])? *(?:\n+|$))[^\n]+)*\n*)+|&lt;(?!(?:a|em|strong|small|s|cite|q|dfn|abbr|data|time|code|var|samp|kbd|sub|sup|i|b|u|mark|ruby|rt|rp|bdi|bdo|span|br|wbr|ins|del|img)\b)\w+(?!:\/|[^\w\s@]*@)\b| *\[([^\]]+)\]: *<!--?([^\s-->]+)&gt;?(?: +["(]([^\n]+)[")])? *(?:\n+|$)))+)\n*/',
			"heading" => '/^ *(#{1,6}) +([^\n]+?) *#* *(?:\n+|$)/'
		]);
	}

	public static function Tables()
	{
		return array_merge(self::GFM(), [
			"nptable" => '/^ *(\S.*\|.*)\n *([-:]+ *\|[-| :]*)\n((?:.*\|.*(?:\n|$))*)\n*/',
			"table" => '/^ *\|(.+)\n *\|( *[-:]+[-| :]*)\n((?: *\|.*(?:\n|$))*)\n*/'
		]);
	}
}

class Markdown
{
	private $options = [
		"gfm" => true,
		"tables" => true,
		"breaks" => true,
		"pedantic" => false,
		"sanitize" => false,
		"mangle" => true,
		"smartLists" => true,
		"silent" => true,
		"langPrefix" => "language-",
		"smartypants" => true,
		"headerPrefix" => '',
		"emotions" => false,
		"emotionClass" => '',
		"emotionDirectory" => '',
		"emotionList" => [],
		"xhtml" => false,
		"outWrapper" => false
	];
	
	public static function Defaults()
	{
		$instance = new Markdown();
		return $instance->options;
	}
	
	private $parser = null;
	private $lexer = null;
	private $renderer = null;
	private $highlighter = null; // not supported :\
	
	public static function substring($string, $len)
	{
		return $len >= strlen($string)
			? ""
			: substr($string, $len, strlen($string))
		;
	}

	public static function escape($html, bool $encode = false) {
		return preg_replace('/\'/', '&#39;',
			preg_replace('/"/', '&quot;',
			preg_replace('/>/', '&gt;',
			preg_replace('/</', '&lt;',
			preg_replace(!$encode ? '/&(?!#?\w+;)/' : '/&/', '&amp;', $html
		)))));
	}
	
	public static function unescape($html) {
		$html = preg_replace_callback('/&([#\w]+);/', function($match) {
			$n = strtolower($cap[1]);
			if ($n === 'colon') return ':';
				if ($n{0} === '#') {
					return $n{1} === 'x'
						? chr(intval(self::substring($n, 2), 16))
						: chr(0 + self::substring($n, 1))
					;
				}
		}, $html);
	}
	
	public static function upreg_replace($pattern, $replacement, $subject)
	{
		return preg_replace($pattern, 
			json_decode('"' . $replacement . '"'),
			$subject
		);
	}
	
	public function __construct($options = null)
	{
		$this->options = array_merge($this->options,
			is_array($options) ? $options : []);
		
		$this->highlighter = array_key_exists("highlighter", $this->options) ? $this->options["highlighter"] : null;
		$this->renderer = new MarkdownRenderer(array_merge($this->options, ["highlighter" => $this->highlighter]));
		
		$options = array_merge($this->options, ["renderer" => $this->renderer]);
		$this->parser = new MarkdownParser($options);
		$this->lexer = new MarkdownLexer($options);
	}
	
	public function parse($src, $styles = "")
	{
		$tokens = $this->lexer->lex($src);
		$out = $this->parser->parse($tokens);
		if ($this->options["outWrapper"]) {
			return '<html><head>' .
				'<link src="stylesheet" rel="' . $styles . '" />' .
				'</head><body>' . $out . '</body></html>';
		} else {
			return $out;
		}
	}
}

}

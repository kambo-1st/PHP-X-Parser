<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpParser\Lexer\JSX;

$code = '<?php
$a = <br />;
$b = 10;';

$lexer = new JSX();
$lexer->startLexing($code);
$tokens = $lexer->getTokens();

echo "Tokens:\n";
foreach ($tokens as $i => $token) {
    if (is_object($token)) {
        $name = token_name($token->id) ?: $token->id;
        echo sprintf("[%d] %s (%d): %s\n", $i, $name, $token->id, json_encode($token->text));
    }
}

// Check if token 8 is correctly tokenized as a variable
if ($tokens[8]->id === 269) { // T_STRING
    echo "\nERROR: Token 8 is a T_STRING when it should be separate tokens!\n";
    echo "Token text: " . json_encode($tokens[8]->text) . "\n";
} else {
    echo "\nOK: Token 8 is not a T_STRING\n";
}
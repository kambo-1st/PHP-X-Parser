# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Module Overview

PHP-X-Parser is a fork of nikic/PHP-Parser that adds JSX syntax support to PHP. This parser serves as the foundation for the entire PHPX ecosystem, enabling React-like component syntax within PHP files. The module maintains backward compatibility with the original PHP-Parser while introducing sophisticated JSX parsing capabilities.

## Critical Rule: Valid PHP in JSX Expressions

**ALL CODE INSIDE JSX EXPRESSIONS `{ }` MUST BE VALID PHP CODE**

The JSX lexer enters `MODE_JSX_EXPR` when it encounters `{` inside JSX. In this mode, it expects valid PHP tokens:
- ❌ JavaScript arrow functions: `() =>` will fail with "unexpected ')'" error
- ✓ PHP arrow functions: `fn() =>` parses correctly
- ❌ Object literals: `{key: value}` is invalid PHP
- ✓ PHP arrays: `["key" => "value"]` works
- ✓ Variables need `$`: `{$var}` not `{var}`

**Parser behavior**: When the lexer sees `<button onClick={() => alert()}>`, it:
1. Enters JSX mode at `<button`
2. Switches to `MODE_JSX_EXPR` at `onClick={`
3. Tries to parse `() =>` as PHP expression
4. Fails because `()` without `fn` is not valid PHP syntax
5. Reports: "Syntax error, unexpected ')'"

**Correct patterns**:
- `<button onClick={fn() => alert()}>` ✓ (requires `fn` keyword)
- `<button onClick={$handleClick}>` ✓ (variable reference)

## Key Architecture Components

### Multi-Mode Lexer Design
The JSX lexer (`lib/PhpParser/Lexer/JSX.php`) implements a state machine with three modes:
- **PHP Mode**: Standard PHP parsing
- **JSX Mode**: Active within JSX elements
- **JSX_EXPR Mode**: JavaScript-like expression parsing within JSX attributes

State transitions occur based on specific tokens:
- `<` followed by identifier/component name → Enter JSX mode
- `{` within JSX → Enter expression mode
- `>` or `/>` → Exit to appropriate mode

### Grammar Integration
JSX syntax is integrated at the grammar level in `grammar/php.y`:
```yacc
expr:
    // ... existing PHP expressions ...
    | jsx_element
    | jsx_fragment
;

jsx_element:
      jsx_opening_element jsx_children jsx_closing_element
    | jsx_self_closing_element
;
```

### AST Node Structure
JSX nodes extend the base Node class:
- `Node\Stmt\JSX\JSXElement` - Complete JSX elements
- `Node\Stmt\JSX\JSXOpeningElement` - Opening tags with attributes
- `Node\Stmt\JSX\JSXClosingElement` - Closing tags
- `Node\Stmt\JSX\JSXAttribute` - Element attributes
- `Node\Expr\JSX\JSXExpressionContainer` - `{expression}` containers
- `Node\Stmt\JSX\JSXFragment` - React fragments `<>...</>`

## Common Development Commands

```bash
# Run all tests
make tests

# Run specific test suites
php vendor/bin/phpunit test/PhpParser/JSX/
php vendor/bin/phpunit test/PhpParser/NodeVisitor/

# Code quality
make phpstan                     # Static analysis
make php-cs-fixer               # Code formatting

# Grammar development
php grammar/rebuildParsers.php  # After modifying grammar/php.y
php bin/generateNodeList.php    # Update node lists after adding AST nodes

# Performance testing
php test/performance/benchmark.php
```

## Key Files and Their Purposes

### Grammar and Parser Generation
- `grammar/php.y`: YACC grammar definition with JSX rules
- `grammar/rebuildParsers.php`: Generates parser classes from grammar
- `lib/PhpParser/Parser/Php7.php`: Generated parser (do not edit directly)

### Lexer Implementation
- `lib/PhpParser/Lexer/JSX.php`: Multi-mode lexer handling JSX tokenization
- `lib/PhpParser/Parser/Tokens.php`: Token constants including JSX-specific tokens

### AST Nodes
- `lib/PhpParser/Node/Stmt/JSX/`: JSX statement nodes
- `lib/PhpParser/Node/Expr/JSX/`: JSX expression nodes
- `lib/PhpParser/NodeVisitor/JSXTransformer.php`: Transforms JSX to function calls

### Testing
- `test/PhpParser/JSX/`: JSX-specific parser tests
- `test/code/parser/jsx/`: JSX parsing test fixtures
- `test/code/prettyPrinter/jsx/`: Pretty printing tests

## JSX Feature Implementation Details

### Supported JSX Features
1. **Elements**: `<div>content</div>`
2. **Self-closing**: `<img src="..." />`
3. **Attributes**: `<div id="main" class={phpVar}>`
4. **Expressions**: `<div>{$count + 1}</div>`
5. **Fragments**: `<>multiple elements</>`
6. **Spread attributes**: `<div {...$props}>`
7. **Event handlers**: `<button onClick={() => doSomething()}>`
8. **Conditional rendering**: `{$show && <div>Visible</div>}`

### Lexer State Management
The lexer tracks nesting depth and mode transitions:
```php
private $jsxDepth = 0;        // Tracks JSX element nesting
private $mode = self::PHP;    // Current parsing mode
private $modeStack = [];      // Mode history for returns
```

### Attribute Parsing
Attributes support multiple value types:
- String literals: `attr="value"`
- Expressions: `attr={$phpExpression}`
- Shorthand: `{$attr}` (expands to `attr={$attr}`)
- No value: `disabled` (boolean attributes)

## Common Development Tasks

### Adding New JSX Features
1. Update grammar in `grammar/php.y`
2. Add/modify token definitions if needed
3. Create AST node classes in appropriate directories
4. Update lexer state machine if new contexts needed
5. Rebuild parsers: `php grammar/rebuildParsers.php`
6. Add test cases to `test/code/parser/jsx/`
7. Update pretty printer if needed

### Debugging Parser Issues
1. Enable debug mode in tests: `--debug` flag
2. Use `Parser::DUMP_NODE_PROPS` for detailed AST output
3. Add temporary debug output in lexer: `$this->debugToken($token)`
4. Check lexer mode transitions in `getNextToken()`
5. Verify grammar conflicts in parser generator output

### Performance Optimization
- Minimize lexer state checks in hot paths
- Cache frequently accessed token mappings
- Avoid regex in high-frequency lexer operations
- Profile with `test/performance/benchmark.php`

## Known Issues and Considerations

### Lexer Complexity
The JSX lexer has significant complexity due to context-sensitive parsing:
- `<` can be less-than or JSX element start
- `{` behavior differs in JSX vs PHP mode
- String interpolation conflicts with JSX expressions

### Grammar Ambiguities
Certain constructs require careful disambiguation:
- Generic types vs JSX: `Array<string>` vs `<Component>`
- Arrow functions in attributes: `onClick={() => ...}`
- Nested JSX within PHP expressions

### Common Pitfalls
1. **Mode Stack Corruption**: Always pair push/pop operations
2. **Token Lookahead**: JSX detection requires multi-token lookahead
3. **Error Recovery**: JSX errors can leave lexer in wrong mode
4. **Unicode Handling**: Component names support Unicode identifiers

### Debugging Tips
- Check `$this->mode` and `$this->jsxDepth` for state issues
- Verify token types match grammar expectations
- Use `NodeDumper` to inspect parsed AST structure
- Compare with test fixtures for expected behavior

## Integration Points

### With PHPX-Compiler
The compiler expects:
- Consistent AST node structure
- Proper position information for error reporting
- Preserved comments and formatting hints

### With PHPX-Framework
Framework relies on:
- Accurate parsing of event handlers
- Proper expression container handling
- Attribute spread operator support

### Extension Points
- Custom node visitors for transformation
- Lexer token preprocessors
- Grammar rule additions via includes
- Pretty printer customization

## Testing Strategy

### Test Categories
1. **Parser Tests**: Grammar rule coverage
2. **Lexer Tests**: Mode transition verification  
3. **Error Tests**: Invalid syntax handling
4. **Integration Tests**: Full parsing pipeline
5. **Performance Tests**: Parsing speed benchmarks

### Critical Test Cases
- Deeply nested JSX elements
- Mixed PHP/JSX contexts
- Edge cases in attribute parsing
- Fragment handling
- Expression container evaluation

### Test File Patterns
- `*.test`: Input/output parser tests
- `*.phpt`: PHP test format for specific scenarios
- `test/fixtures/`: Large realistic examples

## Performance Considerations

- JSX parsing adds ~20% overhead vs pure PHP
- Lexer mode switches are main bottleneck
- AST size increases with JSX nodes
- Pretty printing JSX is computationally intensive

### Optimization Strategies
1. Batch lexer mode transitions
2. Optimize hot path token checks
3. Lazy-load JSX node classes
4. Cache parsed component templates
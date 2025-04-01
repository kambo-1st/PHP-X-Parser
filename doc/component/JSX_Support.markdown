JSX Support
===========

PHPX extends the original PHP-Parser with support for JSX syntax, allowing you to write JSX-like components directly in your PHP code. This document explains the JSX features and how to use them.

JSX Syntax
----------

JSX in PHPX follows a similar syntax to React's JSX, but is integrated into PHP. Here's a basic example:

```php
<?php

function renderUser($user) {
    return <div class="user-profile">
        <h1>{$user->name}</h1>
        <p>Email: {$user->email}</p>
        {!empty($user->avatar) ? <img src={$user->avatar} alt="Profile" /> : null}
    </div>;
}
```

Key features of JSX syntax in PHPX:

1. **Elements**: JSX elements are written using angle brackets (`<` and `>`)
2. **Attributes**: HTML-like attributes can be specified
3. **Expressions**: PHP expressions can be embedded using curly braces `{}`
4. **Self-closing tags**: Elements without children can be self-closed with `/>`
5. **Conditional rendering**: PHP control structures can be used for conditional rendering

JSX AST Structure
----------------

When parsed, JSX code is converted into an AST with the following node types:

- `Expr_JSX`: Represents a JSX element
  - `tag`: The element name
  - `attributes`: Array of attributes
  - `children`: Array of child nodes
- `Expr_JSX_Text`: Represents text content
  - `value`: The text content
- `Expr_JSX_Expression`: Represents an embedded PHP expression
  - `expr`: The PHP expression
- `Expr_JSX_Attribute`: Represents an element attribute
  - `name`: The attribute name
  - `value`: The attribute value

Example AST for a simple JSX element:

```php
Expr_JSX(
    tag: 'div'
    attributes: array(
        0: Expr_JSX_Attribute(
            name: 'class'
            value: 'container'
        )
    )
    children: array(
        0: Expr_JSX_Text(
            value: 'Hello '
        )
        1: Expr_JSX_Expression(
            expr: Expr_Variable(
                name: 'name'
            )
        )
    )
)
```

JSX Expressions
--------------

PHP expressions can be embedded in JSX using curly braces:

```php
<?php

function renderList($items) {
    return <ul>
        {foreach ($items as $item) {
            <li key={$item->id}>{$item->name}</li>
        }}
    </ul>;
}
```

Supported expression types:
- Variables: `{$variable}`
- Function calls: `{someFunction()}`
- Object properties: `{$object->property}`
- Array access: `{$array['key']}`
- Ternary operators: `{$condition ? $true : $false}`
- Control structures: `{if (...) { ... }}`, `{foreach (...) { ... }}`

JSX Attributes
-------------

Attributes can be specified in several ways:

1. **String literals**:
```php
<div class="container"></div>
```

2. **PHP expressions**:
```php
<div class={$className}></div>
```

3. **Boolean attributes**:
```php
<input type="checkbox" checked={$isChecked} />
```

4. **Spread attributes**:
```php
<div {...$props}></div>
```

Component Usage
--------------

JSX components can be used in several ways:

1. **HTML elements**:
```php
<div>
    <h1>Title</h1>
    <p>Content</p>
</div>
```

2. **Custom components**:
```php
<MyComponent prop1={$value1} prop2={$value2}>
    <ChildComponent />
</MyComponent>
```

3. **Fragment syntax**:
```php
<>
    <h1>Title</h1>
    <p>Content</p>
</>
```

Best Practices
-------------

1. **Component organization**:
   - Keep components small and focused
   - Use meaningful names for components
   - Group related components in the same file

2. **Performance**:
   - Avoid unnecessary re-renders
   - Use key props for lists
   - Memoize expensive computations

3. **Code style**:
   - Use consistent indentation
   - Break long JSX expressions into multiple lines
   - Use self-closing tags for empty elements

4. **Error handling**:
   - Validate props and data
   - Use try-catch blocks for error-prone operations
   - Provide fallback content for error states

Limitations
----------

1. **Syntax restrictions**:
   - Some PHP control structures may not work as expected in JSX expressions
   - Complex expressions may need to be moved to separate variables

2. **Performance considerations**:
   - Large JSX trees may impact parsing performance
   - Deeply nested components may require optimization

3. **Tooling support**:
   - Some IDE features may not fully support JSX in PHP
   - Syntax highlighting may need configuration

Examples
--------

Here are some practical examples of JSX usage in PHPX:

1. **Conditional rendering**:
```php
function renderUser($user) {
    return <div>
        {if ($user->isAdmin) {
            <AdminPanel user={$user} />
        } else {
            <UserProfile user={$user} />
        }}
    </div>;
}
```

2. **List rendering**:
```php
function renderList($items) {
    return <ul>
        {foreach ($items as $item) {
            <li key={$item->id}>
                <span>{$item->name}</span>
                {if ($item->isNew) {
                    <span class="new-badge">New</span>
                }}
            </li>
        }}
    </ul>;
}
```

3. **Component composition**:
```php
function renderDashboard($user, $stats) {
    return <div class="dashboard">
        <Header user={$user} />
        <StatsPanel stats={$stats} />
        <ActivityFeed userId={$user->id} />
    </div>;
}
```

For more information about specific JSX features or advanced usage patterns, refer to the other documentation sections or the examples in the repository. 
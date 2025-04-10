<?php

// Basic JSX Examples

// 1. Simple JSX Element
$element1 = <div>Hello World</div>;

// 2. JSX Element with Attributes
$element2 = <div class="container" id={$id}>Hello World</div>;

// 3. JSX Element with Spread Attributes
$element3 = <div {...$props}>Hello World</div>;

// 4. JSX Element with Expressions
$element4 = <div>{$greeting}</div>;

// 5. Self-closing JSX Element
$element5 = <img src="image.jpg" />;

// 6. JSX Element with Nested Elements
$element6 = <div><span>World</span></div>;

// Class Examples with JSX

// 7. Class with JSX Property
class MyComponent {
    public $jsx = <div>Hello World</div>;
}

// 8. Class with JSX Return
class App extends Component {
    public function render() {
        return (
            <div>{$this->foo}</div>
        );
    }
}

$element = <div>{$isLoggedIn ? <span>Welcome</span> : <a>Login</a>}</div>;

$element11 = <>
    <div>Part A</div>
    <div>Part B</div>
</>;

$element = <div>{$hasNotifications && <span>You have new messages</span>}</div>;

$element = <div>
{/* This is a comment */}
<span>Visible</span>
</div>;

$element = <div style={["color" => "red", "fontSize" => "16px"]}>Styled</div>;

$element = <ul>
            { array_map(fn($item) => <li>{$item}</li>, $items) }
        </ul>;


$element = <Some.Component />;

function FruitList(array $items) {
    return (
        <ul>
            { array_map(
                fn($fruit, $index) => <li key={$index}>{$fruit}</li>,
                $items,
                array_keys($items)
            ) }
        </ul>
    );
}

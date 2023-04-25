<?php
class AnyClass {
   
	private string $anyProperty = 'Any text';
   
}

// Создаём экземпляр класса
$anyObject = new AnyClass;

// Получаем св-во anyProperty
$reflectedProperty = new ReflectionProperty(AnyClass::class, 'anyProperty');

// Делаем свойство "anyProperty" доступным
$reflectedProperty->setAccessible(true);

echo ($reflectedProperty)->getValue($anyObject);

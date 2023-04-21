<?php
class AnyClass {
   
	private $anyProperty = 'Any text';
   
}

// Создаём экземпляр класса
$anyObject = new AnyClass;

// Получаем информацию о свойстве anyProperty
$reflectedProperty = new ReflectionProperty(AnyClass::class, 'anyProperty');

// Делаем свойство "anyProperty" доступным
$reflectedProperty->setAccessible(true);

echo ($reflectedProperty)->getValue($anyObject);
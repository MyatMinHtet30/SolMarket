<?php

return [
    'required' => 'Das Feld :attribute ist erforderlich.',
    'string' => 'Das Feld :attribute muss ein Text sein.',
    'numeric' => 'Das Feld :attribute muss eine Zahl sein.',
    'decimal' => 'Das Feld :attribute darf höchstens zwei Nachkommastellen haben.',
    'in' => 'Die Auswahl für :attribute ist ungültig.',
    'image' => 'Das Feld :attribute muss ein Bild sein.',
    'mimes' => 'Das Feld :attribute muss eine Datei vom Typ :values sein.',
    'uploaded' => 'Die Datei für :attribute konnte nicht hochgeladen werden.',
    'min' => [
        'numeric' => 'Das Feld :attribute muss mindestens :min sein.',
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen haben.',
    ],
    'max' => [
        'numeric' => 'Das Feld :attribute darf höchstens :max sein.',
        'string' => 'Das Feld :attribute darf höchstens :max Zeichen haben.',
        'file' => 'Die Datei für :attribute darf höchstens :max KB groß sein.',
    ],
    'attributes' => [
        'title' => 'Titel', 'description' => 'Beschreibung', 'price' => 'Preis',
        'category' => 'Kategorie', 'seller_name' => 'Name', 'image' => 'Foto',
        'district' => 'Bezirk', 'condition' => 'Zustand', 'pickup_details' => 'Abholung', 'q' => 'Suche',
    ],
];

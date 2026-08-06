<?php

namespace App\Support;

use ResourceBundle;

final class ProfileOptions
{
    public static function countries(): array
    {
        $countries = [];
        $bundle = class_exists(ResourceBundle::class) ? ResourceBundle::create('en', 'ICUDATA-region')?->get('Countries') : null;

        if ($bundle) {
            foreach ($bundle as $code => $name) {
                if (preg_match('/^[A-Z]{2}$/', (string) $code)) {
                    $countries[(string) $code] = (string) $name;
                }
            }
        }

        $countries['PH'] = 'Philippines';
        asort($countries, SORT_NATURAL | SORT_FLAG_CASE);

        return $countries;
    }

    public static function nationalities(): array
    {
        return [
            'Afghan', 'Albanian', 'Algerian', 'American', 'Andorran', 'Angolan', 'Antiguan or Barbudan',
            'Argentine', 'Armenian', 'Australian', 'Austrian', 'Azerbaijani', 'Bahamian', 'Bahraini',
            'Bangladeshi', 'Barbadian', 'Belarusian', 'Belgian', 'Belizean', 'Beninese', 'Bhutanese',
            'Bolivian', 'Bosnian or Herzegovinian', 'Botswanan', 'Brazilian', 'British', 'Bruneian',
            'Bulgarian', 'Burkinabe', 'Burundian', 'Cabo Verdean', 'Cambodian', 'Cameroonian', 'Canadian',
            'Central African', 'Chadian', 'Chilean', 'Chinese', 'Colombian', 'Comorian', 'Congolese',
            'Costa Rican', 'Croatian', 'Cuban', 'Cypriot', 'Czech', 'Danish', 'Djiboutian', 'Dominican',
            'Dutch', 'Ecuadorian', 'Egyptian', 'Emirati', 'Equatorial Guinean', 'Eritrean', 'Estonian',
            'Eswatini', 'Ethiopian', 'Fijian', 'Filipino', 'Finnish', 'French', 'Gabonese', 'Gambian',
            'Georgian', 'German', 'Ghanaian', 'Greek', 'Grenadian', 'Guatemalan', 'Guinean',
            'Guyanese', 'Haitian', 'Honduran', 'Hungarian', 'Icelandic', 'Indian', 'Indonesian',
            'Iranian', 'Iraqi', 'Irish', 'Israeli', 'Italian', 'Ivorian', 'Jamaican', 'Japanese',
            'Jordanian', 'Kazakhstani', 'Kenyan', 'Kiribati', 'Kuwaiti', 'Kyrgyzstani', 'Lao', 'Latvian',
            'Lebanese', 'Liberian', 'Libyan', 'Liechtensteiner', 'Lithuanian', 'Luxembourger', 'Malagasy',
            'Malawian', 'Malaysian', 'Maldivian', 'Malian', 'Maltese', 'Marshallese', 'Mauritanian',
            'Mauritian', 'Mexican', 'Micronesian', 'Moldovan', 'Monacan', 'Mongolian', 'Montenegrin',
            'Moroccan', 'Mozambican', 'Myanmar', 'Namibian', 'Nauruan', 'Nepali', 'New Zealander',
            'Nicaraguan', 'Nigerian', 'Nigerien', 'North Korean', 'North Macedonian', 'Norwegian', 'Omani',
            'Pakistani', 'Palauan', 'Palestinian', 'Panamanian', 'Papua New Guinean', 'Paraguayan',
            'Peruvian', 'Polish', 'Portuguese', 'Qatari', 'Romanian', 'Russian', 'Rwandan',
            'Saint Lucian', 'Salvadoran', 'Samoan', 'San Marinese', 'Saudi Arabian', 'Senegalese',
            'Serbian', 'Seychellois', 'Sierra Leonean', 'Singaporean', 'Slovak', 'Slovenian',
            'Solomon Islander', 'Somali', 'South African', 'South Korean', 'South Sudanese', 'Spanish',
            'Sri Lankan', 'Sudanese', 'Surinamese', 'Swedish', 'Swiss', 'Syrian', 'Taiwanese', 'Tajikistani',
            'Tanzanian', 'Thai', 'Timorese', 'Togolese', 'Tongan', 'Trinidadian or Tobagonian', 'Tunisian',
            'Turkish', 'Turkmen', 'Tuvaluan', 'Ugandan', 'Ukrainian', 'Uruguayan', 'Uzbekistani',
            'Vanuatuan', 'Vatican', 'Venezuelan', 'Vietnamese', 'Yemeni', 'Zambian', 'Zimbabwean',
        ];
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $state = 'Tamil Nadu';
        $district = 'Coimbatore';

        // ['name' => village, 'pincode' => pincode]
        $locations = [
            // Coimbatore North
            ['name' => 'Coimbatore', 'pincode' => '641001'],
            ['name' => 'Ganapathy', 'pincode' => '641006'],
            ['name' => 'Saravanampatti', 'pincode' => '641035'],
            ['name' => 'Kavundampalayam', 'pincode' => '641030'],
            ['name' => 'Vellalore', 'pincode' => '641111'],
            ['name' => 'Kalapatti', 'pincode' => '641048'],
            ['name' => 'Chinnavedampatti', 'pincode' => '641049'],
            ['name' => 'Periyanaickenpalayam', 'pincode' => '641020'],

            // Coimbatore South
            ['name' => 'Peelamedu', 'pincode' => '641004'],
            ['name' => 'Singanallur', 'pincode' => '641005'],
            ['name' => 'Ramanathapuram', 'pincode' => '641045'],
            ['name' => 'Kovaipudur', 'pincode' => '641042'],
            ['name' => 'Nanjundapuram', 'pincode' => '641036'],
            ['name' => 'Thondamuthur', 'pincode' => '641109'],
            ['name' => 'Madampatti', 'pincode' => '641021'],
            ['name' => 'Eachanari', 'pincode' => '641021'],
            ['name' => 'Idigarai', 'pincode' => '641104'],

            // Pollachi
            ['name' => 'Pollachi', 'pincode' => '642001'],
            ['name' => 'Kinathukadavu', 'pincode' => '642109'],
            ['name' => 'Anaimalai', 'pincode' => '642101'],
            ['name' => 'Mahalingapuram', 'pincode' => '642101'],
            ['name' => 'Aliyar', 'pincode' => '642101'],
            ['name' => 'Vettaikaranpudur', 'pincode' => '642002'],
            ['name' => 'Udumalpet', 'pincode' => '642126'],
            ['name' => 'Valparai', 'pincode' => '642127'],

            // Mettupalayam
            ['name' => 'Mettupalayam', 'pincode' => '641301'],
            ['name' => 'Sirumugai', 'pincode' => '641302'],
            ['name' => 'Negamam', 'pincode' => '642003'],
            ['name' => 'Periyakulam', 'pincode' => '641305'],
            ['name' => 'Karamadai', 'pincode' => '641104'],
            ['name' => 'Pethampatti', 'pincode' => '641301'],
            ['name' => 'Alathur', 'pincode' => '641301'],
            ['name' => 'Ooty Road', 'pincode' => '641301'],

            // Annur
            ['name' => 'Annur', 'pincode' => '641653'],
            ['name' => 'Palladam', 'pincode' => '641664'],
            ['name' => 'Tirupur Road', 'pincode' => '641606'],
            ['name' => 'Avinashi', 'pincode' => '641654'],
            ['name' => 'Kangeyam', 'pincode' => '638701'],
            ['name' => 'Puliyampatti', 'pincode' => '641666'],
            ['name' => 'Uthukuli', 'pincode' => '638752'],

            // Sulur
            ['name' => 'Sulur', 'pincode' => '641402'],
            ['name' => 'Chettipalayam', 'pincode' => '641201'],
            ['name' => 'Narasimhanaickenpalayam', 'pincode' => '641031'],
            ['name' => 'Irugur', 'pincode' => '641103'],
            ['name' => 'Kurumbapalayam', 'pincode' => '641107'],
            ['name' => 'Malumichampatti', 'pincode' => '641050'],
            ['name' => 'Neelambur', 'pincode' => '641062'],
        ];

        foreach ($locations as $loc) {
            Location::updateOrCreate(
                ['name' => $loc['name'], 'city' => $district],
                ['state' => $state, 'pincode' => $loc['pincode'], 'latitude' => null, 'longitude' => null]
            );
        }

        $count = count($locations);
        $this->command->info("LocationSeeder: {$count} locations seeded for {$district}, {$state}.");
    }
}

<?php
declare(strict_types=1);

// Sample sellers and technicians for the project demo, created by scripts/seed-demo-sellers.php.

$listing = static fn (string $image, string $title, string $category, string $brand, string $model, string $condition, string $price, string $description, string $defects = ''): array => [
    'image' => $image, 'title' => $title, 'category' => $category, 'brand' => $brand, 'model' => $model,
    'condition' => $condition, 'price' => $price, 'description' => $description, 'known_defects' => $defects,
];
$swap = static fn (string $image, string $title, string $category, string $brand, string $model, string $condition, string $wanted, string $wantedDetails, string $cash, string $description, string $defects = ''): array => [
    'image' => $image, 'title' => $title, 'category' => $category, 'brand' => $brand, 'model' => $model, 'condition' => $condition,
    'wanted_item' => $wanted, 'wanted_details' => $wantedDetails, 'cash_preference' => $cash, 'description' => $description, 'known_defects' => $defects,
];
$service = static fn (string $name, string $description, array $brands, string $price): array => [
    'name' => $name, 'description' => $description, 'supported_brands' => $brands, 'supported_models' => [], 'estimated_price' => $price,
];
$phoneBrands = ['Apple', 'Samsung', 'OPPO', 'vivo', 'Xiaomi', 'realme'];
$laptopBrands = ['Lenovo', 'ASUS', 'Acer', 'HP', 'Dell', 'Apple'];

return [
    'seller1' => [
        'email' => 'mikareyes@gmaliek.com', 'name' => 'Mika Gadgets', 'location' => 'San Jose, Antique',
        'listings' => [
            $listing('seller1/galaxy-a54.jpg', 'Samsung Galaxy A54 5G 128GB', 'cellphone', 'Samsung', 'Galaxy A54 5G', 'good', '13500.00', 'Used for one year. Battery still lasts a full day. Includes the clear case.', 'Light scratches on the frame.'),
            $listing('seller1/iphone-11.jpg', 'iPhone 11 64GB Black', 'cellphone', 'Apple', 'iPhone 11', 'good', '14900.00', 'Factory unlocked and working well. Face ID and cameras work.', 'Small marks on the frame.'),
            $listing('seller1/redmi-note-12.jpg', 'Redmi Note 12 128GB Blue', 'cellphone', 'Xiaomi', 'Redmi Note 12', 'like_new', '7800.00', 'Barely used backup phone. Comes with the original charger and cable.'),
            $listing('seller1/realme-c55.jpg', 'realme C55 256GB Green', 'cellphone', 'realme', 'C55', 'good', '6500.00', 'Good everyday phone with plenty of storage.', 'Small scuff on one corner.'),
            $listing('seller1/oppo-a78.jpg', 'OPPO A78 128GB', 'cellphone', 'OPPO', 'A78', 'fair', '5200.00', 'Works normally. Priced lower because of the back panel crack.', 'Small crack at one corner of the back panel.'),
            $listing('seller1/galaxy-s21-fe.jpg', 'Samsung Galaxy S21 FE 5G 128GB Lavender', 'cellphone', 'Samsung', 'Galaxy S21 FE', 'good', '12500.00', 'Fast phone with great cameras. Screen protector already installed.', 'Light marks on the back.'),
            $listing('seller1/iphone-xr.jpg', 'iPhone XR 64GB Red', 'cellphone', 'Apple', 'iPhone XR', 'good', '8900.00', 'All functions work. Battery health is 82 percent. Cable included.'),
            $listing('seller1/vivo-y36.jpg', 'vivo Y36 128GB Gold', 'cellphone', 'vivo', 'Y36', 'like_new', '7200.00', 'Used for two months only. Complete with box.'),
            $listing('seller1/infinix-note-30.jpg', 'Infinix Note 30 256GB Blue', 'cellphone', 'Infinix', 'Note 30', 'good', '5900.00', 'Big battery and fast charging. Good for games and videos.', 'Fingerprint smudges and light wear.'),
            $listing('seller1/nokia-keypad.jpg', 'Nokia 105 Keypad Phone', 'cellphone', 'Nokia', '105', 'good', '650.00', 'Simple backup phone with a long-lasting battery. Dual SIM.'),
        ],
        'exchanges' => [
            $swap('seller1/swap-iphone-13.jpg', 'iPhone 13 128GB Blue', 'cellphone', 'Apple', 'iPhone 13', 'good', 'Samsung Galaxy S23 or similar Android flagship', 'Any color. Screen must have no issues.', 'either', 'Switching to Android. Battery health is 85 percent.', 'Light scratches on the frame.'),
            $swap('seller1/swap-poco-x5.jpg', 'POCO X5 Pro 5G 256GB Black', 'cellphone', 'Xiaomi', 'POCO X5 Pro', 'like_new', 'iPhone 11 or iPhone XR', 'I can add cash depending on the condition.', 'owner_may_add', 'Clean unit with its black case.'),
        ],
    ],
    'seller2' => [
        'email' => 'paolosantos88@gmaliek.com', 'name' => 'Paolo Laptops', 'location' => 'Hamtic, Antique',
        'listings' => [
            $listing('seller2/thinkpad-t480.jpg', 'Lenovo ThinkPad T480 i5 16GB 256GB', 'laptop', 'Lenovo', 'ThinkPad T480', 'good', '16500.00', 'Reliable work laptop with a fresh Windows install. Charger included.', 'Keyboard shows some shine.'),
            $listing('seller2/vivobook-15.jpg', 'ASUS VivoBook 15 Ryzen 5 8GB 512GB', 'laptop', 'ASUS', 'VivoBook 15', 'good', '18900.00', 'Good for school and office work. Battery holds about four hours.', 'Light wear on the palm rest.'),
            $listing('seller2/aspire-5.jpg', 'Acer Aspire 5 i5 8GB 256GB', 'laptop', 'Acer', 'Aspire 5', 'fair', '14000.00', 'Runs well. Priced for its cosmetic wear.', 'Shiny keys and a small palm rest scratch.'),
            $listing('seller2/probook-440.jpg', 'HP ProBook 440 G6 i5 8GB 256GB', 'laptop', 'HP', 'ProBook 440 G6', 'good', '15500.00', 'Former office unit, cleaned and tested. Charger included.'),
            $listing('seller2/latitude-5400.jpg', 'Dell Latitude 5400 i5 16GB 512GB', 'laptop', 'Dell', 'Latitude 5400', 'like_new', '19500.00', 'Very clean business laptop with a fast SSD.'),
            $listing('seller2/ideapad-3.jpg', 'Lenovo IdeaPad 3 i3 8GB 256GB', 'laptop', 'Lenovo', 'IdeaPad 3', 'good', '12500.00', 'Light and quick for school work. Charger included.', 'Small sticker mark on the palm rest.'),
            $listing('seller2/macbook-air-m1.jpg', 'MacBook Air M1 8GB 256GB Silver', 'laptop', 'Apple', 'MacBook Air M1', 'good', '32000.00', 'Battery lasts most of the day. Includes the original charger.', 'Tiny dent on one corner.'),
            $listing('seller2/pavilion-14.jpg', 'HP Pavilion 14 Ryzen 5 16GB 512GB', 'laptop', 'HP', 'Pavilion 14', 'good', '17500.00', 'Fast and quiet. Good for office work and light editing.'),
            $listing('seller2/nitro-5.jpg', 'Acer Nitro 5 i5 GTX 1650 8GB 512GB', 'laptop', 'Acer', 'Nitro 5', 'fair', '26000.00', 'Plays most games on medium settings.', 'Fan gets loud under load. Vents need cleaning.'),
        ],
        'exchanges' => [
            $swap('seller2/swap-tuf-f15.jpg', 'ASUS TUF Gaming F15 i5 RTX 3050', 'laptop', 'ASUS', 'TUF Gaming F15', 'good', 'MacBook Air M1 or M2', 'I need a lighter laptop for school.', 'either', 'Strong gaming laptop. Charger included.'),
            $swap('seller2/swap-chromebook.jpg', 'Lenovo Chromebook 11 4GB 32GB', 'laptop', 'Lenovo', 'Chromebook 11', 'good', 'Android tablet with keyboard', 'Tablet should be 10 inches or bigger.', 'none', 'Good for browsing and online classes.'),
        ],
    ],
    'seller3' => [
        'email' => 'tessadelacruz21@gmaliek.com', 'name' => 'Tessa Tablets and Audio', 'location' => 'San Jose, Antique',
        'listings' => [
            $listing('seller3/ipad-9.jpg', 'iPad 9th Gen 64GB Wi-Fi Silver', 'tablet', 'Apple', 'iPad 9th Gen', 'like_new', '15800.00', 'Used mostly for reading. Includes a gray smart cover.'),
            $listing('seller3/galaxy-tab-a8.jpg', 'Samsung Galaxy Tab A8 64GB', 'tablet', 'Samsung', 'Galaxy Tab A8', 'good', '8900.00', 'Great for videos and online classes.', 'Minor marks on the back.'),
            $listing('seller3/wh-1000xm4.jpg', 'Sony WH-1000XM4 Headphones', 'accessory', 'Sony', 'WH-1000XM4', 'good', '9500.00', 'Noise cancelling works well. Includes the hard case.', 'Ear pads show light wear.'),
            $listing('seller3/flip-5.jpg', 'JBL Flip 5 Bluetooth Speaker Blue', 'accessory', 'JBL', 'Flip 5', 'good', '3200.00', 'Loud and clear. Battery still lasts for hours.'),
            $listing('seller3/mx-master-3.jpg', 'Logitech MX Master 3 Mouse', 'accessory', 'Logitech', 'MX Master 3', 'like_new', '3400.00', 'Comfortable wireless mouse with its USB receiver.'),
            $listing('seller3/xiaomi-pad-6.jpg', 'Xiaomi Pad 6 128GB Gray', 'tablet', 'Xiaomi', 'Pad 6', 'like_new', '14500.00', 'Smooth screen for movies and notes. Includes a blue cover.'),
            $listing('seller3/ipad-mini-5.jpg', 'iPad Mini 5 64GB Wi-Fi Gold', 'tablet', 'Apple', 'iPad Mini 5', 'good', '13000.00', 'Small and easy to carry. Battery still good.', 'Light scratches on the back.'),
            $listing('seller3/liberty-4-earbuds.jpg', 'Soundcore Liberty 4 NC Earbuds', 'accessory', 'Soundcore', 'Liberty 4 NC', 'good', '3500.00', 'Noise cancelling earbuds with charging case.'),
            $listing('seller3/xb13-speaker.jpg', 'Sony SRS-XB13 Mini Speaker Blue', 'accessory', 'Sony', 'SRS-XB13', 'good', '1500.00', 'Small waterproof speaker with strong bass.'),
            $listing('seller3/tab-m10.jpg', 'Lenovo Tab M10 32GB', 'tablet', 'Lenovo', 'Tab M10', 'good', '5500.00', 'Good starter tablet for kids and online classes.', 'Small marks on the frame.'),
        ],
        'exchanges' => [
            $swap('seller3/swap-tab-s6-lite.jpg', 'Samsung Galaxy Tab S6 Lite with S Pen', 'tablet', 'Samsung', 'Galaxy Tab S6 Lite', 'good', 'iPad 9th Gen or newer', 'Wi-Fi model is fine.', 'offerer_may_add', 'Great for notes and drawing. Pen included.'),
            $swap('seller3/swap-smartwatch.jpg', 'Samsung Galaxy Watch 4 44mm Black', 'accessory', 'Samsung', 'Galaxy Watch 4', 'good', 'Noise cancelling wireless earbuds', 'Sony, Soundcore or similar.', 'none', 'Tracks steps, heart rate and sleep. Charger included.', 'Light scratch on the bezel.'),
        ],
    ],
    'seller4' => [
        'email' => 'ramilvillanueva07@gmaliek.com', 'name' => 'Ramil Parts', 'location' => 'Hamtic, Antique',
        'listings' => [
            $listing('seller4/iphone-11-lcd.jpg', 'iPhone 11 LCD Screen Assembly', 'spare_part', 'Apple', 'iPhone 11', 'new', '1800.00', 'Replacement part, unused. Check your model before buying.'),
            $listing('seller4/samsung-battery.jpg', 'Samsung A-Series Replacement Battery', 'spare_part', 'Samsung', 'A-Series', 'new', '650.00', 'Unused replacement battery. Tell me your exact model first.'),
            $listing('seller4/ddr4-sodimm.jpg', '16GB DDR4 Laptop RAM', 'spare_part', 'Generic', 'DDR4 SO-DIMM', 'good', '1900.00', 'Pulled from a working laptop and tested.'),
            $listing('seller4/nvme-ssd.jpg', '512GB NVMe M.2 SSD', 'spare_part', 'Generic', 'M.2 2280', 'good', '1700.00', 'Healthy drive, wiped and ready to use.'),
            $listing('seller4/usb-c-charger.jpg', '65W USB-C Laptop Charger', 'accessory', 'Generic', '65W USB-C', 'good', '850.00', 'Works with most USB-C laptops.', 'Light scuffs on the casing.'),
            $listing('seller4/a50-screen.jpg', 'Samsung Galaxy A50 Screen Assembly', 'spare_part', 'Samsung', 'Galaxy A50', 'new', '1600.00', 'Unused replacement screen with frame. Message me to confirm fit.'),
            $listing('seller4/laptop-keyboard.jpg', 'Replacement Laptop Keyboard', 'spare_part', 'Generic', 'Laptop keyboard', 'new', '900.00', 'New keyboard for common 14-inch laptops. Send your model first.'),
            $listing('seller4/sata-hdd.jpg', '1TB 2.5-inch Laptop Hard Drive', 'spare_part', 'Generic', '2.5-inch SATA', 'good', '1500.00', 'Tested with no bad sectors. Wiped and ready to use.'),
            $listing('seller4/charging-port-flex.jpg', 'USB-C Charging Port Flex Cable', 'spare_part', 'Generic', 'USB-C flex', 'new', '250.00', 'Replacement charging port for many Android phones. Several available.'),
        ],
        'exchanges' => [
            $swap('seller4/swap-sata-ssd.jpg', '1TB 2.5-inch SATA SSD', 'spare_part', 'Generic', '2.5-inch SATA SSD', 'good', '16GB DDR4 laptop RAM', '3200MHz preferred.', 'either', 'Healthy drive, wiped. USB adapter cable included.'),
            $swap('seller4/swap-powerbank.jpg', '20000mAh Power Bank', 'accessory', 'Generic', '20000mAh', 'good', '65W USB-C laptop charger', 'Working charger with cable.', 'none', 'Charges a phone about four times.'),
        ],
    ],
    'tech1' => [
        'type' => 'technician', 'email' => 'jeromebautista@gmaliek.com', 'name' => 'Jerome Phone Repair', 'location' => 'San Jose, Antique',
        'profile' => [
            'headline' => 'Phone screens, batteries and charging ports', 'bio' => 'Fixing phones in San Jose for ten years. Most repairs are done the same day, and you get the old part back.',
            'service_area' => 'San Jose and Hamtic', 'available_hours' => 'Mon to Sat, 9:00 AM to 6:00 PM',
            'specialties' => ['Screen replacement', 'Battery replacement', 'Charging port repair'], 'supported_brands' => $phoneBrands, 'supported_models' => [],
        ],
        'services' => [
            $service('Screen replacement', 'Replace a cracked or unresponsive phone screen. Final price depends on the model.', $phoneBrands, '1500.00'),
            $service('Battery replacement', 'New battery for phones that drain fast or shut down early.', $phoneBrands, '800.00'),
            $service('Charging port repair', 'Fix loose, dirty or dead charging ports.', $phoneBrands, '600.00'),
        ],
    ],
    'tech2' => [
        'type' => 'technician', 'email' => 'lizagarcia@gmaliek.com', 'name' => 'Liza Laptop Clinic', 'location' => 'Hamtic, Antique',
        'profile' => [
            'headline' => 'Laptop repair, upgrades and cleaning', 'bio' => 'Laptop repairs for students and offices. I explain the problem and the price before any work starts.',
            'service_area' => 'Hamtic and San Jose', 'available_hours' => 'Mon to Fri, 8:00 AM to 5:00 PM',
            'specialties' => ['Software troubleshooting', 'Keyboard replacement', 'SSD upgrade', 'Cleaning and thermal paste'], 'supported_brands' => $laptopBrands, 'supported_models' => [],
        ],
        'services' => [
            $service('Software troubleshooting', 'Fix slow startup, errors and Windows problems. Files are backed up first.', $laptopBrands, '500.00'),
            $service('Keyboard replacement', 'Replace keyboards with stuck, missing or dead keys.', $laptopBrands, '1800.00'),
            $service('SSD upgrade with data transfer', 'Install a faster SSD and move your files and system.', $laptopBrands, '700.00'),
            $service('Cleaning and thermal paste', 'Remove dust and replace thermal paste to stop overheating.', $laptopBrands, '600.00'),
        ],
    ],
    'tech3' => [
        'type' => 'technician', 'email' => 'kevinmendoza@gmaliek.com', 'name' => 'Kevin Mobile Fix', 'location' => 'San Jose, Antique',
        'profile' => [
            'headline' => 'Water damage and software repair', 'bio' => 'Dropped your phone in water? Bring it in fast. I also fix software problems and screens.',
            'service_area' => 'San Jose', 'available_hours' => 'Daily, 10:00 AM to 7:00 PM',
            'specialties' => ['Water damage repair', 'Software troubleshooting', 'Screen replacement'], 'supported_brands' => $phoneBrands, 'supported_models' => [],
        ],
        'services' => [
            $service('Water damage cleaning', 'Open, clean and dry the phone, then test each part. Repairs are quoted separately.', $phoneBrands, '700.00'),
            $service('Software reset and update', 'Fix boot loops, freezing and update errors.', $phoneBrands, '400.00'),
            $service('Screen replacement', 'Replace a broken phone screen with a quality part.', $phoneBrands, '1400.00'),
        ],
    ],
    'tech4' => [
        'type' => 'technician', 'email' => 'anavillar@gmaliek.com', 'name' => 'Ana Tablet and Gadget Repair', 'location' => 'Hamtic, Antique',
        'profile' => [
            'headline' => 'Tablets, speakers and headphones', 'bio' => 'I repair tablets and small gadgets that other shops turn away.',
            'service_area' => 'Hamtic and San Jose', 'available_hours' => 'Tue to Sun, 9:00 AM to 5:00 PM',
            'specialties' => ['Tablet screen repair', 'Battery replacement', 'Speaker and headphone repair'], 'supported_brands' => ['Apple', 'Samsung', 'Lenovo', 'Xiaomi', 'JBL', 'Sony'], 'supported_models' => [],
        ],
        'services' => [
            $service('Tablet screen replacement', 'Replace cracked tablet glass or display.', ['Apple', 'Samsung', 'Lenovo', 'Xiaomi'], '2500.00'),
            $service('Tablet battery replacement', 'New battery for tablets that no longer hold a charge.', ['Apple', 'Samsung', 'Lenovo', 'Xiaomi'], '1200.00'),
            $service('Speaker and headphone repair', 'Fix no sound, crackling, charging and Bluetooth problems.', ['JBL', 'Sony', 'Samsung'], '500.00'),
        ],
    ],
    'tech5' => [
        'type' => 'technician', 'email' => 'rodelcastillo@gmaliek.com', 'name' => 'Rodel Tech Services', 'location' => 'San Jose, Antique',
        'profile' => [
            'headline' => 'Board-level repair and data recovery', 'bio' => 'Micro-soldering for phones and laptops that will not turn on. Free diagnosis before any repair.',
            'service_area' => 'San Jose, Hamtic and nearby towns', 'available_hours' => 'Mon to Sat, 9:00 AM to 6:00 PM',
            'specialties' => ['Motherboard repair', 'Charging port repair', 'Data recovery'], 'supported_brands' => array_values(array_unique([...$phoneBrands, ...$laptopBrands])), 'supported_models' => [],
        ],
        'services' => [
            $service('Motherboard diagnosis and repair', 'Find and fix board faults on phones and laptops that will not power on.', ['Apple', 'Samsung', 'Lenovo', 'ASUS', 'HP'], '1500.00'),
            $service('Charging IC repair', 'Replace the charging chip when a device will not charge.', $phoneBrands, '900.00'),
            $service('Data recovery', 'Recover photos and files from damaged phones and drives.', array_values(array_unique([...$phoneBrands, ...$laptopBrands])), '1000.00'),
        ],
    ],
];

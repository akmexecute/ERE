<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Notification\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

final class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'event_key' => 'inquiry.received',
                'channels_json' => ['in_app', 'sms', 'email'],
                'subject_en' => 'New Lead: Inquiry received for :listing_code',
                'subject_am' => 'አዲስ ጥያቄ፡ ለ :listing_code ጥያቄ ቀርቧል',
                'subject_om' => 'Gaaffii Haaraa: :listing_code dhiyaateera',
                'body_en' => 'Hello :name, a buyer has submitted an inquiry for your property :property_code. Message: :message',
                'body_am' => 'ሰላም :name፣ ገዢ ለንብረትዎ :property_code ጥያቄ ልኳል። መልእክት፡ :message',
                'body_om' => 'Akkam :name, bitaan qabeenya keessan :property_code irratti gaaffii ergeera. Ergaa: :message',
                'is_active' => true,
            ],
            [
                'event_key' => 'inquiry.message_received',
                'channels_json' => ['in_app', 'email'],
                'subject_en' => 'New Message: :sender_name replied on :inquiry_code',
                'subject_am' => 'አዲስ መልእክት፡ :sender_name በ :inquiry_code ላይ መልሷል',
                'subject_om' => 'Ergaa Haaraa: :sender_name deebii kenneera',
                'body_en' => ':sender_name sent: :body',
                'body_am' => ':sender_name ልኳል፡ :body',
                'body_om' => ':sender_name ergeera: :body',
                'is_active' => true,
            ],
            [
                'event_key' => 'price.dropped',
                'channels_json' => ['in_app', 'email', 'sms'],
                'subject_en' => 'Price Drop Alert: :property_title is now ETB :new_price',
                'subject_am' => 'የዋጋ ቅናሽ ማሳሰቢያ፡ :property_title አሁን ETB :new_price ሆኗል',
                'subject_om' => 'Gatii Gad-bu\'uu: :property_title amma ETB :new_price ta\'eera',
                'body_en' => 'The asking price for :property_title has dropped from ETB :old_price to ETB :new_price.',
                'body_am' => 'ለ :property_title የቀረበው ዋጋ ከ ETB :old_price ወደ ETB :new_price ቀንሷል።',
                'body_om' => 'Gatiin :property_title ETB :old_price irraa gara ETB :new_price gadi bu\'eera.',
                'is_active' => true,
            ],
            [
                'event_key' => 'listing.status_changed',
                'channels_json' => ['in_app', 'email'],
                'subject_en' => 'Status Update: Listing :listing_code is now :new_status',
                'subject_am' => 'የሁኔታ ለውጥ፡ ዝርዝር :listing_code አሁን :new_status ሆኗል',
                'subject_om' => 'Haala Haaraa: :listing_code amma :new_status ta\'eera',
                'body_en' => 'Your watched listing :listing_code has changed status to :new_status.',
                'body_am' => 'የሚከታተሉት ዝርዝር :listing_code ሁኔታው ወደ :new_status ተለውጧል።',
                'body_om' => 'Gabaasaan hordofaa jirtan :listing_code haala :new_status qabateera.',
                'is_active' => true,
            ],
            [
                'event_key' => 'search.matched',
                'channels_json' => ['in_app', 'email'],
                'subject_en' => 'New Matches for your saved search ":search_name"',
                'subject_am' => 'ለተቀመጠው ፍለጋዎ አዲስ ውጤቶች ":search_name"',
                'subject_om' => 'Barbaacha keessanif bu\'aa haaraa ":search_name"',
                'body_en' => 'A new listing matching your saved search criteria was published: :listing_title (:price_formatted).',
                'body_am' => 'ከፍለጋ መስፈርትዎ ጋር የሚዛመድ አዲስ ዝርዝር ወጥቷል፡ :listing_title (:price_formatted)።',
                'body_om' => 'Gabaasaan haaraan barbaacha keessan wajjin walsimu maxxanfameera: :listing_title (:price_formatted).',
                'is_active' => true,
            ],
            [
                'event_key' => 'ticket.created',
                'channels_json' => ['in_app', 'email'],
                'subject_en' => 'Support Ticket Received: :ticket_code',
                'subject_am' => 'የድጋፍ ጥያቄ ተመዝግቧል፡ :ticket_code',
                'subject_om' => 'Tikkeettiin Deeggarsaa Qaqqabeera: :ticket_code',
                'body_en' => 'Your support request ":subject" (:ticket_code) has been received. Our team will review it shortly.',
                'body_am' => 'የድጋፍ ጥያቄዎ ":subject" (:ticket_code) ደርሶናል። ቡድናችን በቅርቡ ይመለከተዋል።',
                'body_om' => 'Gaaffiin deeggarsaa keessan ":subject" (:ticket_code) qaqqabeera. Gareen keenya dhiyootti ilaala.',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $t) {
            NotificationTemplate::query()->updateOrCreate(
                ['event_key' => $t['event_key']],
                $t
            );
        }
    }
}

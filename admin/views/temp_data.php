<?php
function get_data(){
    return [
        101 => [
            'form_id' => 101,
            'title' => 'Contact Us',
            'entries' => [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'message' => 'Interested in plugin.', 'date' => '2025-07-19 21:00', 'status' => 'unread'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'message' => 'It works great!', 'date' => '2025-07-18 14:22', 'status' => 'read'],
                ['name' => 'Alice Brown', 'email' => 'alice@example.com', 'message' => 'Can I get a demo?', 'date' => '2025-07-17 09:30', 'status' => 'unread'],
                ['name' => 'Bob Lee', 'email' => 'bob@example.com', 'message' => 'Thanks for your help.', 'date' => '2025-07-16 16:45', 'status' => 'read'],
                ['name' => 'Chris Evans', 'email' => 'chris@example.com', 'message' => 'How do I reset my password?', 'date' => '2025-07-15 11:10', 'status' => 'unread'],
                ['name' => 'Diana Prince', 'email' => 'diana@example.com', 'message' => 'Is there a free trial?', 'date' => '2025-07-14 13:05', 'status' => 'read'],
                ['name' => 'Frank Castle', 'email' => 'frank@example.com', 'message' => 'I need support.', 'date' => '2025-07-13 09:50', 'status' => 'unread'],
                ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'message' => 'Love the UI!', 'date' => '2025-07-12 17:30', 'status' => 'read'],
                ['name' => 'Henry Ford', 'email' => 'henry@example.com', 'message' => 'Can I get a refund?', 'date' => '2025-07-11 08:40', 'status' => 'unread'],
                ['name' => 'Ivy Lane', 'email' => 'ivy@example.com', 'message' => 'How to integrate with Zapier?', 'date' => '2025-07-10 15:20', 'status' => 'read'],
            ],
        ],
        202 => [
            'form_id' => 202,
            'title' => 'Bug Report',
            'entries' => [
                ['name' => 'Mark Johnson', 'email' => 'mark@example.com', 'message' => 'Form not submitting', 'date' => '2025-07-17 10:15', 'status' => 'unread'],
                ['name' => 'Sara White', 'email' => 'sara@example.com', 'message' => 'Error on page load', 'date' => '2025-07-16 11:05', 'status' => 'read'],
                ['name' => 'Paul Allen', 'email' => 'paul@example.com', 'message' => 'Button not clickable', 'date' => '2025-07-15 14:30', 'status' => 'unread'],
                ['name' => 'Olivia King', 'email' => 'olivia@example.com', 'message' => 'Page crashes on submit', 'date' => '2025-07-14 09:45', 'status' => 'read'],
                ['name' => 'Liam Scott', 'email' => 'liam@example.com', 'message' => 'Dropdown not working', 'date' => '2025-07-13 16:20', 'status' => 'unread'],
                ['name' => 'Mia Clark', 'email' => 'mia@example.com', 'message' => 'Validation error', 'date' => '2025-07-12 12:10', 'status' => 'read'],
                ['name' => 'Noah Lee', 'email' => 'noah@example.com', 'message' => '404 error on page', 'date' => '2025-07-11 18:55', 'status' => 'unread'],
                ['name' => 'Emma Davis', 'email' => 'emma@example.com', 'message' => 'Slow loading', 'date' => '2025-07-10 10:40', 'status' => 'read'],
                ['name' => 'Lucas Brown', 'email' => 'lucas@example.com', 'message' => 'Misaligned fields', 'date' => '2025-07-09 13:25', 'status' => 'unread'],
                ['name' => 'Sophia Wilson', 'email' => 'sophia@example.com', 'message' => 'Captcha not displaying', 'date' => '2025-07-08 15:15', 'status' => 'read'],
            ],
        ],
        303 => [
            'form_id' => 303,
            'title' => 'Newsletter Signup',
            'entries' => [
                ['name' => 'Tom Green', 'email' => 'tom@example.com', 'message' => 'Looking forward to updates.', 'date' => '2025-07-15 08:20', 'status' => 'read'],
                ['name' => 'Lisa Black', 'email' => 'lisa@example.com', 'message' => 'Subscribed!', 'date' => '2025-07-14 13:55', 'status' => 'unread'],
                ['name' => 'Jack White', 'email' => 'jack@example.com', 'message' => 'Excited for the newsletter.', 'date' => '2025-07-13 10:10', 'status' => 'read'],
                ['name' => 'Megan Blue', 'email' => 'megan@example.com', 'message' => 'How often do you send emails?', 'date' => '2025-07-12 11:45', 'status' => 'unread'],
                ['name' => 'Oscar Red', 'email' => 'oscar@example.com', 'message' => 'Please add me.', 'date' => '2025-07-11 09:30', 'status' => 'read'],
                ['name' => 'Pam Violet', 'email' => 'pam@example.com', 'message' => 'Can I unsubscribe anytime?', 'date' => '2025-07-10 14:20', 'status' => 'unread'],
                ['name' => 'Quinn Indigo', 'email' => 'quinn@example.com', 'message' => 'Will you share my email?', 'date' => '2025-07-09 16:05', 'status' => 'read'],
                ['name' => 'Rita Orange', 'email' => 'rita@example.com', 'message' => 'Looking for exclusive offers.', 'date' => '2025-07-08 12:50', 'status' => 'unread'],
                ['name' => 'Steve Pink', 'email' => 'steve@example.com', 'message' => 'Newsletter looks great.', 'date' => '2025-07-07 17:35', 'status' => 'read'],
                ['name' => 'Tina Gray', 'email' => 'tina@example.com', 'message' => 'How to update my email?', 'date' => '2025-07-06 08:25', 'status' => 'unread'],
            ],
        ],
        404 => [
            'form_id' => 404,
            'title' => 'Feedback',
            'entries' => [
                ['name' => 'Eve Adams', 'email' => 'eve@example.com', 'message' => 'Great service!', 'date' => '2025-07-13 17:40', 'status' => 'read'],
                ['name' => 'Sam Carter', 'email' => 'sam@example.com', 'message' => 'Could be improved.', 'date' => '2025-07-12 12:10', 'status' => 'unread'],
                ['name' => 'Nina Fox', 'email' => 'nina@example.com', 'message' => 'Love the new features.', 'date' => '2025-07-11 19:25', 'status' => 'read'],
                ['name' => 'Uma Stone', 'email' => 'uma@example.com', 'message' => 'Support was helpful.', 'date' => '2025-07-10 15:00', 'status' => 'unread'],
                ['name' => 'Victor Lane', 'email' => 'victor@example.com', 'message' => 'Mobile view is nice.', 'date' => '2025-07-09 13:45', 'status' => 'read'],
                ['name' => 'Wendy Moss', 'email' => 'wendy@example.com', 'message' => 'Found a small bug.', 'date' => '2025-07-08 11:20', 'status' => 'unread'],
                ['name' => 'Xander Ray', 'email' => 'xander@example.com', 'message' => 'Easy to use.', 'date' => '2025-07-07 16:10', 'status' => 'read'],
                ['name' => 'Yara Bell', 'email' => 'yara@example.com', 'message' => 'Can you add dark mode?', 'date' => '2025-07-06 09:55', 'status' => 'unread'],
                ['name' => 'Zane Wolf', 'email' => 'zane@example.com', 'message' => 'Notifications are useful.', 'date' => '2025-07-05 14:40', 'status' => 'read'],
                ['name' => 'Amy Snow', 'email' => 'amy@example.com', 'message' => 'Thanks for listening.', 'date' => '2025-07-04 10:30', 'status' => 'unread'],
            ],
        ],
    ];
}
-- SecureLink OS V5
-- Email Studio templates. Runtime schema hardening adds newer optional columns on legacy databases.
CREATE TABLE IF NOT EXISTS communication_templates(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 template_key TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL,
 subject TEXT NOT NULL,
 message TEXT NOT NULL,
 channel TEXT NOT NULL DEFAULT 'email',
 active INTEGER NOT NULL DEFAULT 1,
 display_order INTEGER NOT NULL DEFAULT 100,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT
);

INSERT OR IGNORE INTO communication_templates(template_key,name,subject,message,channel,active) VALUES
('credit_50','$50 Credit Processed Successfully','Your $50 SecureLink credit was processed','Hi {{first_name}},

Great news — your $50 credit has been processed successfully on our side. Please allow the provider billing cycle to reflect it on your account.

If you have any questions, simply reply to this email.

SecureLink
{{website}}','email',1),
('credit_100_today','$100 Credit Processed Successfully Today','Your $100 SecureLink credit was processed today','Hi {{first_name}},

Your $100 credit was processed successfully today. Please allow the provider billing cycle to reflect it on your account.

Thank you for choosing SecureLink.

SecureLink
{{website}}','email',1),
('new_deals','Great New Deals Available','New SecureLink deals are available','Hi {{first_name}},

We have some great new Internet, Mobility, TV and Security deals available. Feel free to check the latest offers on our website:

{{website}}

If you want, reply to this email and we can help compare the best options for you.

SecureLink','email',1),
('order_received','Order Request Received','We received your SecureLink request','Hi {{first_name}},

We received your service request and our team is reviewing it. We will contact you if anything else is needed.

You can sign in anytime to track your order:
{{website}}

SecureLink','email',1),
('order_activated','Service Activated','Your SecureLink service is active','Hi {{first_name}},

Good news — your service has been activated successfully.

You can review your services, order history and savings in your SecureLink account:
{{website}}

Thank you for choosing SecureLink.','email',1),
('appointment_reminder_v5','Appointment Reminder','Reminder: your SecureLink appointment','Hi {{first_name}},

This is a reminder about your upcoming service appointment. Please make sure an adult is available and the equipment area is accessible.

Check your SecureLink account for the latest appointment details:
{{website}}

SecureLink','email',1),
('documents_needed','Documents Needed','Action needed for your SecureLink order','Hi {{first_name}},

We need additional information or documents to continue processing your order. Please sign in to your SecureLink account and open the Documents section:
{{website}}

SecureLink','email',1),
('referral_update','Referral Reward Update','Update on your SecureLink referral','Hi {{first_name}},

There is an update on your SecureLink referral reward. Sign in to your account to view the latest status and reward details:
{{website}}

Thank you for referring your friends and family.','email',1),
('follow_up','Quick Follow-up','Following up from SecureLink','Hi {{first_name}},

Just following up to see if you still need help with Internet, Mobility, TV, Security or Home Phone services.

You can review current offers here:
{{website}}

Reply anytime and we will be happy to help.

SecureLink','email',1),
('thank_you','Thank You','Thank you for choosing SecureLink','Hi {{first_name}},

Thank you for choosing SecureLink. We appreciate your business.

If you need help with your services, upgrades, referrals or future offers, you can always reach us through your SecureLink account or reply to this email.

{{website}}','email',1);

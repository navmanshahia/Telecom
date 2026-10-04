-- 003_provider_offers_2026_10.sql
-- Customer-facing offer catalogue transcribed from user-supplied TELUS West (updated Sep 25)
-- and Rogers Oct 1 sheets. Internal promo codes/job-aid links are intentionally not exposed.
INSERT OR IGNORE INTO providers(name,description,status,display_order) VALUES
('TELUS','TELUS home and mobility offers','active',10),
('Rogers','Rogers home and mobility offers','active',20);

-- Prevent duplicate seeding by exact provider + name.
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,bill_credit,term_months,speed_data,fine_print,status)
SELECT p.id,'mobility','5G+ Select 100GB CAN','100GB 5G+ non-shareable Canada data; unlimited Canada calling and international SMS/MMS; Family discount when applicable.',40,65,0,NULL,'100GB 5G+','Price shown after eligible PAB and Mobility & Home discounts. Active eligible home service required. Verify eligibility before processing.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='5G+ Select 100GB CAN');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,term_months,speed_data,fine_print,status)
SELECT p.id,'mobility','5G+ Complete 100GB CAN-US','100GB 5G+ Canada-US data; unlimited Canada-US calling; international SMS/MMS; long distance to 27 countries; Easy Roam; 5-year price guarantee.',45,80,24,'100GB 5G+ CAN-US','Price shown after eligible PAB, Mobility & Home and 24-month bill-credit offer. Active eligible home service required. Verify current eligibility.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='5G+ Complete 100GB CAN-US');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,term_months,speed_data,fine_print,status)
SELECT p.id,'mobility','10GB 5G CAN','10GB shock-free Canada data; unlimited Canada calling and international SMS/MMS; Pick Your Perk options.',30,40,NULL,'10GB 5G','Price shown after eligible PAB discount. Active eligible home service required.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='10GB 5G CAN');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,speed_data,fine_print,status)
SELECT p.id,'mobility','5G+ Complete 100GB CAN','100GB 5G+ Canada data; unlimited Canada calling; international SMS/MMS; long distance to 27 countries; Easy Roam; 5-year price lock.',50,75,'100GB 5G+','Price shown after eligible PAB and Mobility & Home discounts. Active eligible home service required.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='5G+ Complete 100GB CAN');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,speed_data,fine_print,status)
SELECT p.id,'mobility','5G+ Complete 175GB CAN-US','175GB 5G+ Canada-US data; unlimited Canada-US calling; international SMS/MMS; long distance to 27 countries; Easy Roam; 5-year price lock.',55,80,'175GB 5G+ CAN-US','Price shown after eligible PAB and Mobility & Home discounts. Active eligible home service required.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='5G+ Complete 175GB CAN-US');

INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,term_months,speed_data,fine_print,status)
SELECT p.id,'internet','PureFibre 500 Mbps','Unlimited internet usage.',60,80,24,'500 Mbps','Price includes eligible PAB and Mobility & Home discounts. 2-year price lock shown on source sheet. Availability varies by address.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='PureFibre 500 Mbps');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,term_months,speed_data,fine_print,status)
SELECT p.id,'internet','PureFibre 1 Gig / 1.5 Gig','Unlimited internet usage; 5-year price lock on eligible Shock-Free offer.',75,95,60,'1 Gig or 1.5 Gig','Price includes eligible PAB and Mobility & Home discounts. Speed/availability depends on address.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='PureFibre 1 Gig / 1.5 Gig');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,term_months,speed_data,fine_print,status)
SELECT p.id,'internet','PureFibre 3 Gig','Unlimited internet usage; 5-year price lock on eligible Shock-Free offer.',85,105,60,'3 Gig','Price includes eligible PAB and Mobility & Home discounts. Availability depends on address.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='PureFibre 3 Gig');

INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,bill_credit,term_months,fine_print,status)
SELECT p.id,'tv','Core TV & Sports','Live TV package bundled with eligible internet.',35,50,50,24,'Bundled pricing. One-time credit/install eligibility must be verified. Internal back-pocket promo codes are not displayed.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Core TV & Sports');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,bill_credit,term_months,fine_print,status)
SELECT p.id,'tv','Core TV + 1 Theme Pack & Sports','Live TV package bundled with eligible internet.',40,65,50,24,'Bundled pricing. Verify package and installation eligibility.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Core TV + 1 Theme Pack & Sports');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,bill_credit,term_months,fine_print,status)
SELECT p.id,'tv','Core TV + 1 Theme Pack & 1 Premium','Live TV package bundled with eligible internet.',50,80,50,24,'Bundled pricing. Verify package and installation eligibility.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Core TV + 1 Theme Pack & 1 Premium');
INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,bill_credit,term_months,fine_print,status)
SELECT p.id,'tv','Core TV + 1 Theme Pack & 2 Premium','Live TV package bundled with eligible internet.',55,85,50,24,'Bundled pricing. Verify package and installation eligibility.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Core TV + 1 Theme Pack & 2 Premium');

INSERT INTO deals(provider_id,category,name,description,monthly_price,regular_price,term_months,fine_print,status)
SELECT p.id,'security','HomeView','Includes one eligible TELUS camera (indoor, outdoor or doorbell).',18,23,36,'3-year term; source shows a $5 monthly discount. Verify equipment and installation eligibility.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='HomeView');
INSERT INTO deals(provider_id,category,name,description,monthly_price,term_months,fine_print,status)
SELECT p.id,'security','SmartHome+ Video','Video subscription plus one TELUS camera.',17,36,'Professional install may be extra. Verify current equipment and install terms.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='SmartHome+ Video');
INSERT INTO deals(provider_id,category,name,description,monthly_price,term_months,fine_print,status)
SELECT p.id,'security','SmartHome+ Premium','Video + automation with eligible camera, smart bulbs, plugs and smart door lock.',33,36,'Equipment shown in source is subject to eligibility and availability.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='SmartHome+ Premium');
INSERT INTO deals(provider_id,category,name,description,monthly_price,term_months,fine_print,status)
SELECT p.id,'security','SmartHome+ Ultimate','Video + automation with eligible doorbell camera, door lock, sensors and safety devices.',39,36,'Equipment shown in source is subject to eligibility and availability.','active' FROM providers p WHERE p.name='TELUS' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='SmartHome+ Ultimate');

-- Rogers SMB / nationwide mobility
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'mobility','Essential BYOD SMB','Canada + USA; 200GB high-speed data; coverage across Canada & USA; roaming included for 24 months.',45,'200GB','SMB BYOD offer. Verify business eligibility and current roaming terms.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Essential BYOD SMB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'mobility','Popular BYOD SMB','Canada + USA + Mexico + Caribbean; unlimited data; roaming included for 24 months; calling to 27 countries included.',60,'Unlimited','SMB BYOD offer. Verify business eligibility and included destinations.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Popular BYOD SMB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'mobility','Ultimate BYOD SMB Global 64','Unlimited data; roaming in 64 countries worldwide; satellite calling included.',75,'Unlimited','SMB BYOD global offer. Verify business eligibility and current 64-country list.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Ultimate BYOD SMB Global 64');
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'mobility','Essential Term SMB','Canada + USA; 200GB high-speed data; coverage across Canada & USA; roaming included for 24 months.',55,'200GB','SMB term offer. Verify term/device eligibility.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Essential Term SMB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'mobility','Popular Term SMB','Canada + USA + Mexico + Caribbean; unlimited data; roaming included for 24 months; calling to 27 countries included.',70,'Unlimited','SMB term offer. Verify term/device eligibility.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Popular Term SMB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'mobility','Ultimate Term SMB Global 64','Unlimited data; roaming in 64 countries worldwide; satellite calling included.',85,'Unlimited','SMB term global offer. Verify term/device eligibility and current destination list.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Ultimate Term SMB Global 64');

-- Rogers promotional SMB variants shown on Oct 1 sheet
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'mobility','Essential Promo BYOD SMB','Canada + USA; 200GB high-speed data; coverage across Canada & USA; roaming included for 24 months.',30,'200GB','Promotional SMB BYOD pricing; verify current eligibility before quoting.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Essential Promo BYOD SMB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'mobility','Popular Promo BYOD SMB','Canada + USA + Mexico + Caribbean; unlimited data; roaming included for 24 months; calling to 27 countries.',40,'Unlimited','Promotional SMB BYOD pricing; verify current eligibility before quoting.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Popular Promo BYOD SMB');
INSERT INTO deals(provider_id,category,name,description,monthly_price,speed_data,fine_print,status)
SELECT p.id,'mobility','Ultimate Promo BYOD SMB Global 64','Unlimited data; roaming in 64 countries worldwide; satellite calling included.',55,'Unlimited','Promotional SMB BYOD pricing; verify current eligibility and destination list.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Ultimate Promo BYOD SMB Global 64');

-- Rogers residential internet (BC/AB/MB/SK)
INSERT INTO deals(provider_id,category,name,description,monthly_price,term_months,speed_data,fine_print,status)
SELECT p.id,'internet','Rogers Internet 500','Residential internet in British Columbia, Alberta, Manitoba and Saskatchewan.',60,24,'500 Mbps','2-year term; no installation fee shown. Customers without Fido or Rogers mobile service: +$10/month. Address eligibility applies.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Rogers Internet 500');
INSERT INTO deals(provider_id,category,name,description,monthly_price,term_months,speed_data,fine_print,status)
SELECT p.id,'internet','Rogers Internet 1 Gig','Residential internet in British Columbia, Alberta, Manitoba and Saskatchewan.',70,24,'1 Gbps','2-year term; no installation fee shown. Customers without Fido or Rogers mobile service: +$10/month. Address eligibility applies.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Rogers Internet 1 Gig');
INSERT INTO deals(provider_id,category,name,description,monthly_price,term_months,speed_data,fine_print,status)
SELECT p.id,'internet','Rogers Internet 2 Gig','Residential internet in British Columbia, Alberta, Manitoba and Saskatchewan.',80,24,'2 Gbps','2-year term; no installation fee shown. Customers without Fido or Rogers mobile service: +$10/month. Address eligibility applies.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Rogers Internet 2 Gig');

INSERT INTO deals(provider_id,category,name,description,monthly_price,fine_print,status)
SELECT p.id,'tv','Rogers TV Basic','Rogers TV package.',35,'Package availability and channel lineup depend on address and service eligibility.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Rogers TV Basic');
INSERT INTO deals(provider_id,category,name,description,monthly_price,fine_print,status)
SELECT p.id,'tv','Rogers TV Plus','Rogers TV package.',60,'Package availability and channel lineup depend on address and service eligibility.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Rogers TV Plus');
INSERT INTO deals(provider_id,category,name,description,monthly_price,fine_print,status)
SELECT p.id,'tv','Rogers TV Ultimate','Rogers TV package.',90,'Package availability and channel lineup depend on address and service eligibility.','active' FROM providers p WHERE p.name='Rogers' AND NOT EXISTS(SELECT 1 FROM deals d WHERE d.provider_id=p.id AND d.name='Rogers TV Ultimate');

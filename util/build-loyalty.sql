create user loyalty;
set password for loyalty = password('loyalty');
create database loyalty;
grant all privileges on loyalty.* to loyalty;

use loyalty;
source /tmp/dump.sql;

update Partner set Pass = 'awdeveloper', GoogleClientId = 'googleClientId', GoogleClientSecret = 'googleSecret', LiveClientId = 'liveClientId', LiveClientSecret = 'liveSecret', YahooClientId = 'yahooClientId', YahooClientSecret = 'yahooSecret', RequestPrivateKey = null, AolClientId = 'aolClientId', AolClientSecret = 'aolSecret';
update PartnerMailbox set Pass = 'awdeveloper';
update PartnerCallback set Pass = 'awdeveloper';
update PartnerCallback set URL = 'awardwallet.docker' where URL = 'awardwallet.com';
update PartnerApiKey set ApiKey = concat(PartnerApiKeyID, ':awdeveloper');
update PartnerApiKey set ApiKey = 'test:awdeveloper' where PartnerID = 3 limit 1;
update PartnerApiKey set ApiKey = 'awardwallet:awdeveloper' where PartnerID = 30 limit 1;

update PartnerCallback set URL = replace(URL, 'https://awardwallet.com', 'http://awardwallet.docker');

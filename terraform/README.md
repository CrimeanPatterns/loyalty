Инициализация нового окружения

1. Создайте новую AWS организацию
2. Создайте новый профиль aws на jenkins для этой организации
3. Обновите скрипт update-proxy-whitelist.py в репо serverscripts - добавьте сбор ip с нового профиля awa, и сделайте git pull в /var/lib/jenkins/repositories/serverscripts. Убедитесь что джоба update-proxy-whitelist в jenkins успешна.
1. Создайте VPC:
```shell
terraform apply -target module.vpc
```
2. Одобрите vpc peering connections со стороны awardwallet и webdriver-cluster
3. Добавьте раутинг в webdriver-cluster:terraform/modules/vpc/main.tf:aws_default_route_table и примените terraform для webdriver-cluster
4. Добавьте разрешение на tcp/24224, tcp/9200 с адресов организации (вида 172.35.0.0/16) в security группу elasticsearch основного аккаунта AWS 
4. Добавьте разрешение на tcp/25-27 с адресов организации (вида 172.35.0.0/16) в security группу smtp-servers основного аккаунта AWS
2. Сгенерируйте пароли
```shell
terraform apply -target random_password.mysql_password -target aws_ssm_parameter.mysql_password
```
2. Создайте образы. Смотрите инструкции в packer/README.md
3. Создайте сервисы
```shell
terraform apply -target module.services
```
5. Создайте новый конфиг базы данных на jenkins: /var/lib/jenkins/vars/mysql/my-new-loyalty-env.cnf
4. Получите структуру базы mysql скриптом frontend:util/sync/getLoyaltyDatabaseStructure.sh
5. Импортируйте эту структуру в созданный mysql server:
```shell
j /var/lib/jenkins/workspace/Frontend/deploy-frontend/util/sync/getLoyaltyDatabaseStructure.sh | mysql --defaults-file=/var/lib/jenkins/vars/mysql/my-new-loyalty-env.cnf
```
Предполагается что shared_database будет показывать на основную базу. Если это не так, то таблицы из shared в дампе лишние.
6. Добавьте новый конфиг в frontend:util/sync/updateAmazonDatabase.sh
7. Запустите джобу provider-tables-sync и убедитесь что она работает.
6. Добавьте новый конфиг в frontend:util/sync/updateAirports.sh
7. Запустите /var/lib/jenkins/workspace/Frontend/deploy-frontend/util/sync/updateAirports.sh. Штатно она зашедулена в jenkins/frontend:daily-jobs, но нам надо синхронизировать аэропорты до запуска воркеров. 
3. Создайте selenium
```shell
terraform apply -target module.selenium
```
4. Добавьте конфиг в джобу selenium-monitor и сделайте деплой. Адрес консул сервера: selenium-consul.infra.awardwallet.com 
4. Зайдите на инстанс mongo-1 и выполните инициализацию репликасета
```shell
cd /opt/mongo
docker compose exec mongo /tmp/scripts/init.sh
docker compose exec mongo mongo
rs.status()
```
3. Выкатите полный сценарий terraform
```shell
terraform apply
```
4. Добавьте organization_id в serverscripts/terraform/main.tf и примените, для разрешения на скачивание образов
5. Добавьте синхронизацию файлов engine в https://jenkins.awardwallet.com/job/deploy-engine/configure : создать папку /mnt/efs/engine-files-some-cluster, разрешить на нее запись всем, примонтировать в fstab, добавить вызовы синхронизации и отправки сообщений в раббит
6. Добавьте маску подсети в группу seleniums в основной организации awardwallet, для доступа к макам
7. Добавьте маску подсети в https://github.com/AwardWallet/webdriver-cluster/blob/577fe14266d6e90be10be857264c46f7c85b0043/terraform/environments/awardwallet-prod-2/main.tf#L65-L65 для поиска маков
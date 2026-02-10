loyalty
=======
Назначение
--------
Парсинг сайтов программ лояльности 

YOU MUST UNPACK  src/AppBundle/Engine/Engine packed.zip

Запуск в докер
--------------

1. Установите docker: https://www.docker.com  
2. Установите docker-compose если вы на linux: https://docs.docker.com/compose/install/
3. Создайте Github Access Token тут: https://github.com/settings/tokens/new?scopes=read:packages,repo&description=parserbox-web

   Увеличьте или уберите время жизни токена.

   Сгенерированный токен понадобится на следующих шагах. Запишите его.
3. Скачайте проект и сабмодули:
   Если гитхаб запрашивает имя пользователя и пароль - используйте Github Access Token в качестве пароля
```bash
git clone --recursive https://github.com/AwardWallet/loyalty.git 
cd loyalty
git submodule foreach "git checkout master && git pull" 
```
4. Авторизуйтесь на docker.awardwallet.com (http пароль от staging.awardwallet.com):
```bash
docker login docker.awardwallet.com
Username: VPupkin
Password: 
Login Succeeded
```
5. Настройте маппинг пользователей
```bash
echo LOCAL_USER_ID=`id -u $USER` >.env
```
6. Запустите docker-compose
```bash
docker network create awardwallet
docker-compose up -d
```
Это запустит все нужные сервисы для работы с сайтом. Пока не закрывайте это окно, здесь мы сможем
увидеть логи если что то пойдет не так.

7. Перейдите в консоль контейнера:
Откройте новое окно терминала, перейдите в папку где лежат исходники, выполните:
```bash
// docker-compose exec php console
docker-compose exec php bash
```
Вы должны увидеть запрос командной строки вида:
```bash
user@loyalty:/www/loyalty$ 
```
12. Установите github access token для composer:
```shell
composer config github-oauth.github.com <ваш GitHub Access Token>
```
13. Залогиньтесь в npm
```bash
npm login --scope=@awardwallet --registry=https://npm.pkg.github.com
Username: <Ваше имя пользователя GitHub в нижнем регистре>
Password: <ваш GitHub Access Token>
Email: (this IS public) <ваш @awardwallet.com email>
Logged in as vsilantyev to scope @awardwallet on https://npm.pkg.github.com/.
```
8. Запустите настройку среды разработки
```bash
user@loyalty:/www/loyalty$ php docker/setup-dev.php
setting up dev environment
...
*** setup successful ***
```
9. Прогоните тесты
```bash
user@loyalty:/www/loyalty$ vendor/bin/codecept build
user@loyalty:/www/loyalty$ vendor/bin/codecept run tests/unit/
```
10. Настройка xdebug

По-умолчанию xdebug будет подключаться к вашей IDE по адресу `host.docker.internal:9003` (хост: `host.docker.internal`, порт: `9003`), будет работать из коробки для Docker Desktop for Mac и Docker Desktop for Windows.
Если у вас Docker установлен в отдельной виртуалке (Parallels, VMWare, VirtualBox etc.) или Docker бежит прямо на вашем linux-хосте, то нужно перекрыть адрес хоста и порта (если необходимо) в файле `docker-compose-local.yml` (положить рядом с `docker-compose.yml` в корне проекта).
```yaml
version: '2.4'

services:
  php:
    environment:
      - XDEBUG_CONFIG=client_host=192.168.56.1 client_port=9009
```
Добавить подключение `docker-compose-local.yml` в файле `.env` (если файла нет, то создать пустой новый) в конце переменной `COMPOSE_FILE`:
```
COMPOSE_FILE=docker-compose.yml:docker-compose-local.yml
```
Если по какой-то причине у вас изменен локальный домен для сайта, то нужно также перекрыть его в файле `docker-compose-local.yml` в переменной `PHP_IDE_CONFIG`:
```yaml
    environment:
      - PHP_IDE_CONFIG=serverName=loyalty.some.docker
```

Отправка запроса
----------------

checkAccount
```bash
curl -H 'X-Authentication: awardwallet:awdeveloper' -d '{"provider":"testprovider", "login": "Chrome.Simple", "userId": "1", "priority": 1, "userData": "{\"accountId\": 1}"}' http://loyalty.docker/account/check
```

checkConfirmation
```bash
curl -H 'X-Authentication: awardwallet:awdeveloper' -d '{"provider":"testprovider", "fields":[{"code": "ConfNo", "value":"12345"}, {"code":"LastName", "value":"Smith"}], "userId": "1", "priority": 1}' http://loyalty.docker/confirmation/check
```

Сброс очереди
-------------
```bash
docker-compose exec rabbitmq rabbitmqctl purge_queue loyalty_check_account_awardwallet
```

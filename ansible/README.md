Пример настройки mysql
-----------------------
```shell
cd ansible/playbooks
OBJC_DISABLE_INITIALIZE_FORK_SAFETY=YES ansible --ask-become-pass -i "192.168.2.137," all --module-name include_role --args name=mysql
```
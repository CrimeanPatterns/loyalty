```shell script
cd packer
packer init .
packer build -var-file=environments/ra-awardwallet.hcl images.pkr.hcl
```
Сборка только образа селениум:
```shell script
packer build -var-file=environments/ra-awardwallet.hcl -only '*selenium*' images.pkr.hcl
```

Локальная сборка:
```shell
packer build -var-file=environments/ra-awardwallet.hcl -only '*selenium*' -var ansible_dir=~/www/ansible -var vpc_name=Main -var subnet_name=Main-B images.pkr.hcl
```

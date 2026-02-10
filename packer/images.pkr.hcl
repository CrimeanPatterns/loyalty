packer {
  required_plugins {
    amazon = {
      version = ">= 1.1.1"
      source = "github.com/hashicorp/amazon"
    }
  }
}

variable "ansible_dir" {
  type = string
  default = "~/ansible"
}

variable "aws_profile" {
  type = string
  default = "default"
}

variable "vpc_name" {
  type = string
  default = "main"
}

variable "subnet_name" {
  type = string
  default = "main-b"
}

variable "memcached_memory" {
  type = number
  description = "memcached memory in mb"
}

variable "ssm_path" {
  type = string
}

source "amazon-ebs" "ecs" {
  instance_type = "t3.small"
  encrypt_boot = true
  profile = var.aws_profile
  source_ami_filter {
    filters = {
      name = "amzn2-ami-ecs-hvm-2.0*x86_64-ebs"
      root-device-type = "ebs"
      virtualization-type = "hvm"
    }
    most_recent = true
    owners = ["591542846629"]
  }
  vpc_filter {
    filters = {
      "tag:Name": var.vpc_name
    }
  }
  subnet_filter {
    filters = {
      "tag:Name": var.subnet_name
    }
  }
  ssh_username = "ec2-user"
}

source "amazon-ebs" "amazon_linux_2_x86" {
  instance_type = "t3.small"
  encrypt_boot = true
  profile = var.aws_profile
  source_ami_filter {
    filters = {
      name = "amzn2-ami-hvm-2.0*x86_64-ebs"
      root-device-type = "ebs"
      virtualization-type = "hvm"
    }
    owners = ["137112412989"] // amazon
    most_recent = true
  }
  vpc_filter {
    filters = {
      "tag:Name": var.vpc_name
    }
  }
  subnet_filter {
    filters = {
      "tag:Name": var.subnet_name
    }
  }
  ssh_username = "ec2-user"
}

data "amazon-ami" "amazon_linux_2_arm" {
  filters = {
    virtualization-type = "hvm"
    name = "amzn2-ami-hvm-2.0*arm64-gp2"
    root-device-type = "ebs"
  }
  owners = ["137112412989"]
  most_recent = true
}

source "amazon-ebs" "amazon_linux_2_arm" {
  instance_type = "t4g.small"
  encrypt_boot = true
  profile = var.aws_profile
  source_ami = data.amazon-ami.amazon_linux_2_arm.id
  vpc_filter {
    filters = {
      "tag:Name": var.vpc_name
    }
  }
  subnet_filter {
    filters = {
      "tag:Name": var.subnet_name
    }
  }
  ssh_username = "ec2-user"
}

build {
  name = "consul"
  source "source.amazon-ebs.amazon_linux_2_arm" {
    ami_name = join("-", ["consul", formatdate("YYYY-MM-DD-hh-mm", timestamp())])
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/configure-base.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{change_hostname: false}"]
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/deploy-role.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{role: 'services/consul'}"]
  }
}

build {
  name = "memcached"
  source "source.amazon-ebs.amazon_linux_2_arm" {
    ami_name = join("-", ["memcached", formatdate("YYYY-MM-DD-hh-mm", timestamp())])
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/configure-base.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{change_hostname: false}"]
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/deploy-role.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{role: 'services/memcached', memory: ${var.memcached_memory}}"]
  }
}

build {
  name = "files"
  source "source.amazon-ebs.amazon_linux_2_arm" {
    ami_name = "files"
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/configure-base.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{change_hostname: false}"]
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/deploy-role.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{role: 'services/engine-files-nfs-server'}"]
  }
}

data "amazon-parameterstore" "mysql-password" {
  name = "${var.ssm_path}/mysql_password"
  with_decryption = true
  profile = var.aws_profile
}

build {
  name = "mysql"
  source "source.amazon-ebs.amazon_linux_2_x86" {
    ami_name = "mysql"
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/configure-base.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{change_hostname: false}"]
  }
  provisioner "ansible" {
    playbook_file = pathexpand("../ansible/playbooks/deploy-role.yml")
    extra_arguments = ["-e", "{role: 'services/mysql', mysql_password: '${data.amazon-parameterstore.mysql-password.value}'}"]
  }
}

build {
  name = "mongo"
  source "source.amazon-ebs.amazon_linux_2_x86" {
    ami_name = "mongo"
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/configure-base.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{change_hostname: false}"]
  }
  provisioner "ansible" {
    playbook_file = pathexpand("../ansible/playbooks/deploy-role.yml")
    extra_arguments = ["-e", "{role: 'services/mongo'}"]
  }
}

build {
  name = "rabbitmq"
  source "source.amazon-ebs.amazon_linux_2_arm" {
    ami_name = "rabbitmq"
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/configure-base.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{change_hostname: false}"]
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/deploy-role.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{role: 'services/rabbitmq-standalone', instance_name: 'rabbitmq'}"]
  }
}

build {
  name = "ecs-based"
  source "source.amazon-ebs.ecs" {
    ami_name = join("-", ["ecs-based", formatdate("YYYY-MM-DD-hh-mm", timestamp())])
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/configure-base.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{change_hostname: false}"]
  }
}

build {
  name = "selenium"
  source "source.amazon-ebs.ecs" {
    ami_name = join("-", ["selenium", formatdate("YYYY-MM-DD-hh-mm", timestamp())])
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/configure-base.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{change_hostname: false, swap_size: 0}"]
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/deploy-role.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "role=ami/selenium", "--become"]
  }
}

build {
  name = "nat-server"
  source "source.amazon-ebs.amazon_linux_2_arm" {
    ami_name = join("-", ["nat-server", formatdate("YYYY-MM-DD-hh-mm", timestamp())])
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/configure-base.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "{swap_size: 0}"]
  }
  provisioner "ansible" {
    playbook_file = pathexpand("${var.ansible_dir}/playbooks/deploy-role.yml")
    command = pathexpand("${var.ansible_dir}/bin/ansible-playbook-wrapper.sh")
    extra_arguments = ["-e", "role=nat-server", "--become"]
  }
}

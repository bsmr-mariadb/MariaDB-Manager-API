#!/bin/bash
#
#  Part of MariaDB Manager API
#
# This file is distributed as part of SkySQL Manager.  It is free
# software: you can redistribute it and/or modify it under the terms of the
# GNU General Public License as published by the Free Software Foundation,
# version 2.
#
# This program is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
# FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
# details.
#
# You should have received a copy of the GNU General Public License along with
# this program; if not, write to the Free Software Foundation, Inc., 51
# Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
#
# Copyright 2013 (c) SkySQL Ab
#
# Author: Marcos Amaral
# Date: July 2013
#
#
# This script is responsible for the installation of the MariaDB-Manager-GREX package
# on the target node.
#
# Parameters:
# $1 IP of the node
# $2 TaskID for the invoking Task
# $3 Other parameters (root_password is necessary)

. ./functions.sh

nodeip=$1
taskid=$2
params=$(echo $3 | tr "&" "\n")

# Parameter parsing and validation
for param in $params
do
        param_name=$(echo $param | cut -d = -f 1)
        param_value=$(echo $param | cut -d = -f 2)

        if [[ "$param_name" == "rootpassword" ]]; then
                rootpwd=$param_value
        fi
done

if [[ "$rootpwd" == "" ]]; then
        logger -p user.error -t MariaDB-Manager-Task "Error: system password parameter not defined."
        exit 1
fi

scripts_installed=0;

# Checking if SkySQL Remote Execution Scripts are installed
ssh_return=$(ssh_agent_command "$nodeip" \
	"sudo /usr/local/sbin/skysql/NodeCommand.sh test $taskid $api_host")
if [[ "$ssh_return" == "0" ]]; then
        logger -p user.info -t MariaDB-Manager-Task "Info: MariaDB Manager API Agent already installed."
	scripts_installed=1;
fi

# Copying repository information to node
ssh_return=$(ssh_put_file "$nodeip" "steps/repo/MariaDB.repo" "/etc/yum.repos.d/MariaDB.repo")
if [[ "$ssh_return" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Failed to write MariaDB repository file"
	set_error "Failed to install MariaDB Repository"
	exit 1
fi
ssh_return=$(ssh_put_file "$nodeip" "steps/repo/SkySQL.repo" "/etc/yum.repos.d/SkySQL.repo")
if [[ "$ssh_return" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Failed to write SkySQL repository file"
	set_error "Failed to install SkySQL Repository"
	exit 1
fi
ssh_return=$(ssh_put_file "$nodeip" "steps/repo/Percona.repo" "/etc/yum.repos.d/Percona.repo")
if [[ "$ssh_return" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Failed to write Percona repository file"
	set_error "Failed to install Percona Repository"
	exit 1
fi

ssh_command "$nodeip" "yum -y clean all"
if [[ !scripts_installed ]]; then
	ssh_command "$nodeip" "yum -y install MariaDB-Manager-GREX"
else
	ssh_command "$nodeip" "yum -y update MariaDB-Manager-GREX"
fi

ssh_return=$(ssh_agent_command "$nodeip" "exit 0")
if [[ "$ssh_return" != "0" ]]; then
	set_error "Agent user creation failed."
	logger -p user.error -t MariaDB-Manager-Task "Error: Failed to create agent user."
	exit 1
fi

# Getting current node systemid and nodeid
task_json=$(api_call "GET" "task/$taskid" "fields=systemid,nodeid")
json_error "$task_json"
if [[ "$json_err" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Unable to determine System ID and Node ID."
	set_error "Error: Unable to determine System and Node ID."
	exit 1
fi

# We have a very simple JSON return to parse, since we asked for two specific fields
# both are which are guaranteed to be numeric, therefore there is no need to worry
# about spaces and quotes in the return data. So we can use a very simplistic approach
# to the parsing.
task_fields=$(echo $task_json | sed -e 's/{"task":{//' -e 's/}}//')

system_id=$(echo $task_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "systemid") print $2; }')
node_id=$(echo $task_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "nodeid") print $2; }')

# Check to see if the node is really in a position to run the scripts, i.e. connected
ssh_return=$(ssh_agent_command "$nodeip" \
	"sudo /usr/local/sbin/skysql/NodeCommand.sh test $taskid $api_host")
if [[ "$ssh_return" != "0" ]]; then
	set_error "Agent script installation failed."
	logger -p user.error -t MariaDB-Manager-Task "Error: Failed to install agent scripts."
	exit 1
fi

# Updating node state
state_json=$(api_call "PUT" "system/$system_id/node/$node_id" "state=connected")
if [[ $? != 0 ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Failed to update the node state."
	set_error "Failed to update the node state."
	exit 1
fi
json_error "$state_json"
if [[ "$json_err" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Failed to update the node state."
	set_error "Failed to update the node state."
	exit 1
fi

logger -p user.info -t MariaDB-Manager-Task "Info: SkySQL Galera remote execution agent successfully installed."
exit 0

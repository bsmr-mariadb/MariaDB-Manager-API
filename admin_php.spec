%define _topdir	 	%(echo $PWD)/
%define name		admin_php
%define release		1
%define version 	0.1
%define buildroot %{_topdir}/%{name}-%{version}-%{release}root
%define install_path	/var/www/html/

BuildRoot:	%{buildroot}
Summary: 		Admin cnsole backend
License: 		GPL
Name: 			%{name}
Version: 		%{version}
Release: 		%{release}
Source: 		%{name}-%{version}-%{release}.tar.gz
Prefix: 		/
Group: 			Development/Tools
#Requires:		
#BuildRequires:		

%description
PHP sripts that implements admin console backend 

%prep

%setup -q

%build


%install
mkdir -p $RPM_BUILD_ROOT%{install_path}{consoleAPI,restfulapi,restfulapitest}
mkdir -p $RPM_BUILD_ROOT%/etc/scdsapi/

cp -R consoleAPI $RPM_BUILD_ROOT%{install_path}
cp -R restfulapi $RPM_BUILD_ROOT%{install_path}
cp -R restfulapitest $RPM_BUILD_ROOT%{install_path}
cp restfulapi/api.ini $RPM_BUILD_ROOT%/etc/scdsapi/
rm restfulapi/api.ini

%clean


%files
%defattr(-,root,root)
%{install_path}
%{install_path}consoleAPI/
%{install_path}consoleAPI/*
%{install_path}restfulapi/
%{install_path}restfulapi/*
%{install_path}restfulapitest/
%{install_path}restfulapitest/*
/etc/scdsapi/api.ini

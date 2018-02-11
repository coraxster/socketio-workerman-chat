FROM nginx
MAINTAINER Dmitry Kuzmin rockwith@me.com

COPY sms_service.template /etc/nginx/conf.d/

CMD envsubst '$$NGINX_HOST $$NGINX_PORT $$FPM_HOST $$FPM_PORT' < /etc/nginx/conf.d/sms_service.template > /etc/nginx/conf.d/default.conf && nginx -g 'daemon off;'
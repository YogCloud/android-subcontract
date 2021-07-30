FROM h6play/apk-subcontract:v1
WORKDIR /opt
COPY . /opt
RUN composer update
EXPOSE 9501
ENTRYPOINT ["php", "start.php"]

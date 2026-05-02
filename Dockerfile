FROM php:8.2-cli
WORKDIR /usr/src/elitechwiz
COPY . /usr/src/elitechwiz
CMD [ "php", "./elitechwiz.php" ]

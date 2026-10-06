# Primary source capture

Date: 2026-10-06
Tool: urllib.request GET
URL: https://raw.githubusercontent.com/danharrin/monorepo-split-github-action/64498811856d440ceeea37737feac43ffcb2c623/Dockerfile
SHA256: 07152107dab54315b344b67bab2d34dc00bf66e4aacd574fa943444d83530a2b

```text
# THIS IS BASE IMAGE
FROM php:8.1-cli-alpine

RUN apk add --no-cache git

# directory inside docker
WORKDIR /splitter

# make local content available inside docker - copies to /splitter
COPY . .

# see https://nickjanetakis.com/blog/docker-tip-86-always-make-your-entrypoint-scripts-executable
ENTRYPOINT ["php", "/splitter/entrypoint.php"]

```

FROM node:slim

ENV NODE_ENV=production
ENV PORT=4100
ENV UUID=dc523887-b4de-4e48-bdd2-11ab20bddbf9

WORKDIR /app

COPY dist dist/

EXPOSE 4100

CMD ["node", "./dist/apps/node-vless/main.js"]

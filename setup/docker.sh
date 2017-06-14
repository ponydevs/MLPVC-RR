#!/bin/bash
docker run -p 127.0.0.1:9201:9200 -v VCElastic:/usr/share/elasticsearch/data elasticsearch

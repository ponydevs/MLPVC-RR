#!/bin/bash
my_dir=`dirname $0`
pg_dump -U mlpvc-rr -d mlpvc-rr --inserts > ${my_dir}/mlpvc-rr_full.pg.sql

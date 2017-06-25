#!/bin/bash
my_dir=`dirname $0`
pg_dump -U mlpvc-rr -d mlpvc-rr -s --inserts > ${my_dir}/mlpvc-rr.pg.sql

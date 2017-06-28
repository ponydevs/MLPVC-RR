#!/bin/bash
pg_dump -U postgres -d mlpvc-rr -s --inserts > mlpvc-rr.pg.sql

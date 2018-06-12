#!/bin/bash
pg_dump -U postgres -d mlpvc-rr --inserts --column-inserts > mlpvc-rr_full.pg.sql

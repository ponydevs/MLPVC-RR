#!/bin/bash
pg_dump -U postgres -d mlpvc-rr --inserts --column-inserts --data-only --exclude-table=phinxlog > mlpvc-rr_data.pg.sql

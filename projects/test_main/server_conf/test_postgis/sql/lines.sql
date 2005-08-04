BEGIN;
CREATE TABLE "lines" (gid serial, "fid" int8);
SELECT AddGeometryColumn('','lines','the_geom','-1','MULTILINESTRING',2);
INSERT INTO "lines" (gid,"fid", "the_geom") VALUES ('0','0',GeometryFromText('MULTILINESTRING ((-0.207970084558716 51.9083472587978 ,-0.479719049515558 51.5360511768069 ,0.134433611286905 51.8893248312508 ,-0.42536925652419 51.3458269013371 ,0.381725169397632 51.856714955456 ,-0.411781808276348 51.1773425430639 ))',-1) );

ALTER TABLE ONLY "lines" ADD CONSTRAINT "lines_pkey" PRIMARY KEY (gid);
END;
/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 *
 * @copyright 2008 Camptocamp SA
 */

package org.cartoweb.stats.imports;

import java.io.File;
import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.net.URLDecoder;
import java.sql.Timestamp;
import java.util.Calendar;
import java.util.GregorianCalendar;
import java.util.HashMap;
import java.util.Map;
import java.util.SimpleTimeZone;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class WmsReader extends StatsReader {
    private static final Pattern URL_PATTERN = Pattern.compile("([^ ]+) ([^ ]+) ([^ ]+) \\[([^\\]]+)\\] \"GET [^\"?]*\\?([^\"]+) HTTP/[\\d\\.]+\" \\d+ \\d+");
    private static final Pattern PARAM_PATTERN = Pattern.compile("([^=]+)=([^&]*)&?");
    private static final Pattern TIME_PATTERN = Pattern.compile("(\\d{2})/(\\w{3})/(\\d{4}):(\\d{2}):(\\d{2}):(\\d{2}) ([+-])(\\d{2})(\\d{2})");
    private static final Map<String, Integer> MONTH;

    private static final double RESOLUTION = 96;
    private static final double INCHES_IN_M = 39.3701;

    private final Pattern mapIdRegExp;

    public WmsReader(File file, SideTables sideTables, boolean wantLayers, String mapIdRegExp, boolean skipErrors) throws IOException {
        super(file, sideTables, wantLayers, skipErrors);
        this.mapIdRegExp = Pattern.compile(mapIdRegExp);
    }

    protected WmsReader(SideTables sideTables, boolean wantLayers, String mapIdRegExp, boolean skipErrors) {
        super(sideTables, wantLayers, skipErrors);
        this.mapIdRegExp = Pattern.compile(mapIdRegExp);
    }

    protected StatsRecord parse(String curLine) {
        if (curLine.toLowerCase().contains("request=getmap")) {
            Matcher matcher = URL_PATTERN.matcher(curLine);
            if (matcher.matches()) {
                String params = matcher.group(5);
                try {
                    Map<String, String> fields = parseParams(params);
                    if (fields == null) {
                        parseError("Invalid input line", curLine);
                    }

                    Matcher mapIdMatcher = mapIdRegExp.matcher(curLine);
                    if (!mapIdMatcher.find()) {
                        parseError("Cannot find the mapId (project) from line", curLine);
                    } else if (mapIdMatcher.groupCount() != 1) {
                        parseError("Cannot get the mapId (project) from line", curLine);
                    }

                    return createRecord(matcher.group(1), matcher.group(3), matcher.group(4), mapIdMatcher.group(1), fields);
                } catch (RuntimeException ex) {
                    parseError("Line with error (" + ex.getClass().getSimpleName() + " - " + ex.getMessage() + ")", curLine);
                    return null;
                }
            } else {
                parseError("Invalid input line", curLine);
                return null;
            }
        } else {
            //not a WMS request
            return null;
        }
    }

    private Map<String, String> parseParams(String params) {
        Matcher matcher = PARAM_PATTERN.matcher(params);
        if (matcher.find()) {
            Map<String, String> fields = new HashMap<String, String>();
            int prevEnd;
            do {
                fields.put(decode(matcher.group(1).toLowerCase()), decode(matcher.group(2)));
                prevEnd = matcher.end();
            } while (matcher.find());
            if (prevEnd != params.length()) {
                return null;
            }

            return fields;
        } else {
            return null;
        }
    }

    private StatsRecord createRecord(String address, String user, String time, String mapId, Map<String, String> fields) {
        StatsRecord result = new StatsRecord();
        int generalMapId = sideTables.generalMapId.get(toLowerCase(mapId));
        result.setGeneralMapid(generalMapId);
        result.setGeneralIp(address);
        result.setGeneralSecurityUser(sideTables.user.get(user.equals("-") ? null : toLowerCase(user), generalMapId));
        result.setGeneralTime(parseTime(time));
        fillLayers(result, fields.get("layers"), generalMapId);
        fillBbox(result, fields.get("bbox"));
        result.setImagesMainmapWidth(getFloat(fields, "width") != null ? Math.round(getFloat(fields, "width")) : null);
        result.setImagesMainmapHeight(getFloat(fields, "height") != null ? Math.round(getFloat(fields, "height")) : null);
        result.setLocationScale(getScale(result));

        //not in WMS "by design":
        //result.setImagesMainmapSize(sideTables.imagesMainmapSize.get(String.format("%d x %d", width, height), generalMapid));

        return result;
    }

    private Float getScale(StatsRecord result) {
        final double minx = result.getBboxMinx();
        final double maxx = result.getBboxMaxx();
        final Integer width = result.getImagesMainmapWidth();
        if (width != null && minx != 0 && maxx != 0 && minx != maxx) {
            return (float) ((maxx - minx) / (width / (RESOLUTION * INCHES_IN_M)));
        } else {
            return null;
        }
    }

    private Timestamp parseTime(String time) {
        Matcher matcher = TIME_PATTERN.matcher(time);
        if (!matcher.matches()) {
            throw new RuntimeException("Cannot parse time [" + time + "]");
        }

        int offset = (Integer.parseInt(matcher.group(8), 10) * 60 + Integer.parseInt(matcher.group(9), 10)) * 60 * 1000;
        if (matcher.group(7).equals("-")) {
            offset = -offset;
        }
        int day = Integer.parseInt(matcher.group(1));
        Integer month = MONTH.get(matcher.group(2).toLowerCase());
        int year = Integer.parseInt(matcher.group(3));
        int hour = Integer.parseInt(matcher.group(4));
        int minute = Integer.parseInt(matcher.group(5));
        int second = Integer.parseInt(matcher.group(6));

        if (month == null) {
            throw new RuntimeException("Cannot parse month in time [" + time + "]");
        }

        Calendar calendar = new GregorianCalendar(new SimpleTimeZone(offset, "tmp"));
        calendar.set(year, month, day, hour, minute, second);
        calendar.set(Calendar.MILLISECOND, 0);
        return new Timestamp(calendar.getTimeInMillis());
    }

    /**
     * Decode the %XX stuff.
     */
    private static String decode(String s) {
        try {
            return URLDecoder.decode(s, "UTF-8");
        } catch (UnsupportedEncodingException e) {
            throw new RuntimeException(e);
        }
    }

    static {
        MONTH = new HashMap<String, Integer>();
        MONTH.put("jan", 0);
        MONTH.put("feb", 1);
        MONTH.put("mar", 2);
        MONTH.put("apr", 3);
        MONTH.put("may", 4);
        MONTH.put("jun", 5);
        MONTH.put("jul", 6);
        MONTH.put("aug", 7);
        MONTH.put("sep", 8);
        MONTH.put("oct", 9);
        MONTH.put("nov", 10);
        MONTH.put("dec", 11);
    }
}
